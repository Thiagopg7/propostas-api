<?php

namespace App\Providers;

use App\Http\Controllers\ProposalController;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $idempotentOperations = $this->idempotentOperations();
            $businessRuleErrors = $this->businessRuleErrorResponses();

            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    $signature = strtoupper($operation->method).' '.$path->path;

                    if (in_array($signature, $idempotentOperations, true)) {
                        $operation->addParameters([$this->idempotencyKeyParameter()]);
                        $operation->addResponse($this->errorResponse(
                            409,
                            'Idempotency-Key já utilizada com um payload diferente, ou requisição com a mesma chave ainda em processamento.',
                        ));
                    }

                    if (isset($businessRuleErrors[$signature])) {
                        [$code, $description] = $businessRuleErrors[$signature];
                        $operation->addResponse($this->errorResponse($code, $description));
                    }
                }
            }
        });
    }

    /**
     * Operations (method + documented path) protected by the idempotency middleware.
     *
     * @return list<string>
     */
    private function idempotentOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('idempotency', $route->gatherMiddleware(), true)) {
                continue;
            }

            $path = ltrim(Str::after($route->uri(), 'api/v1'), '/');

            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $operations[] = $method.' '.$path;
                }
            }
        }

        return $operations;
    }

    private function idempotencyKeyParameter(): Parameter
    {
        return Parameter::make('Idempotency-Key', 'header')
            ->required(true)
            ->description('Chave única (UUID) que garante a idempotência da operação. Repetir a mesma chave devolve a resposta original, sem duplicar registros.')
            ->setSchema(Schema::fromType(new StringType));
    }

    /**
     * Business-rule error responses thrown inside the service layer, which Scramble
     * cannot infer from the controller signature.
     *
     * @return array<string, array{int, string}>
     */
    private function businessRuleErrorResponses(): array
    {
        $conflict = [409, 'Conflito de versão (optimistic lock): a proposta foi modificada por outra requisição.'];
        $invalidTransition = [422, 'Transição de status não permitida para o estado atual da proposta.'];

        $actionResponses = [
            'update' => $conflict,
            'submit' => $invalidTransition,
            'approve' => $invalidTransition,
            'reject' => $invalidTransition,
            'cancel' => $invalidTransition,
        ];

        $responses = [];

        foreach (Route::getRoutes() as $route) {
            if (ltrim((string) $route->getControllerClass(), '\\') !== ProposalController::class) {
                continue;
            }

            $response = $actionResponses[$route->getActionMethod()] ?? null;

            if ($response === null) {
                continue;
            }

            $path = ltrim(Str::after($route->uri(), 'api/v1'), '/');

            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $responses[$method.' '.$path] = $response;
                }
            }
        }

        return $responses;
    }

    private function errorResponse(int $code, string $description): Response
    {
        $schema = new ObjectType;
        $schema->addProperty('message', new StringType);
        $schema->setRequired(['message']);

        return Response::make($code)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($schema));
    }
}
