<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
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

            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    if (in_array(strtoupper($operation->method).' '.$path->path, $idempotentOperations, true)) {
                        $operation->addParameters([$this->idempotencyKeyParameter()]);
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
}
