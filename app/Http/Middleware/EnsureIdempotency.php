<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => ['O cabeçalho Idempotency-Key é obrigatório.'],
            ]);
        }

        $fingerprint = $this->fingerprint($request);
        $reservation = $this->reserve($key, $fingerprint);

        if ($reservation === null) {
            return $this->replay($key, $fingerprint);
        }

        $response = $next($request);

        if ($response->getStatusCode() < Response::HTTP_BAD_REQUEST) {
            $this->persist($reservation, $response);
        } else {
            $reservation->delete();
        }

        return $response;
    }

    private function reserve(string $key, string $fingerprint): ?IdempotencyKey
    {
        try {
            return IdempotencyKey::create([
                'key' => $key,
                'request_hash' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function replay(string $key, string $fingerprint): JsonResponse
    {
        $stored = IdempotencyKey::query()->where('key', $key)->first();

        if ($stored === null || ! hash_equals($stored->request_hash, $fingerprint)) {
            return new JsonResponse([
                'message' => 'A Idempotency-Key informada já foi utilizada com outro payload.',
            ], Response::HTTP_CONFLICT);
        }

        if ($stored->response_status === null) {
            return new JsonResponse([
                'message' => 'Uma requisição com esta Idempotency-Key ainda está em processamento.',
            ], Response::HTTP_CONFLICT);
        }

        return new JsonResponse($stored->response_body, $stored->response_status);
    }

    private function persist(IdempotencyKey $reservation, Response $response): void
    {
        $reservation->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode((string) $response->getContent(), true),
        ]);
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            (string) $request->getContent(),
        ]));
    }
}
