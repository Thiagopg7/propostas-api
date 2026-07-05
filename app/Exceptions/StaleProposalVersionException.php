<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaleProposalVersionException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'A proposta foi modificada por outra requisição. Recarregue e tente novamente.'],
            JsonResponse::HTTP_CONFLICT,
        );
    }
}
