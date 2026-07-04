<?php

namespace App\Http\Resources;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'product' => $this->product,
            'monthly_value' => $this->monthly_value,
            'status' => $this->status->value,
            'origin' => $this->origin->value,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'client' => ClientResource::make($this->whenLoaded('client')),
        ];
    }
}
