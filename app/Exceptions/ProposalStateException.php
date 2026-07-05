<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalStateException extends Exception
{
    public static function notEditable(): self
    {
        return new self('Apenas propostas em rascunho podem ser editadas.');
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['message' => $this->getMessage()],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
