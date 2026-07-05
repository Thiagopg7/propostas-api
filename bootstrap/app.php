<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Models\Client;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();
            $model = $previous instanceof ModelNotFoundException ? $previous->getModel() : null;

            $message = match ($model) {
                Client::class => 'Cliente não encontrado.',
                Proposal::class => 'Proposta não encontrada.',
                null => 'Rota não encontrada.',
                default => 'Recurso não encontrado.',
            };

            return new JsonResponse(['message' => $message], JsonResponse::HTTP_NOT_FOUND);
        });
    })->create();
