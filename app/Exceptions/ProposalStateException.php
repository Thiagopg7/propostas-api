<?php

namespace App\Exceptions;

use App\Enums\ProposalStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalStateException extends Exception
{
    public static function notEditable(): self
    {
        return new self('Apenas propostas em rascunho podem ser editadas.');
    }

    public static function cannotTransition(ProposalStatus $from, ProposalStatus $target): self
    {
        return new self("Não é possível alterar o status de {$from->value} para {$target->value}.");
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
