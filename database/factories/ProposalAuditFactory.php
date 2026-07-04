<?php

namespace Database\Factories;

use App\Enums\ProposalAuditEvent;
use App\Models\Proposal;
use App\Models\ProposalAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalAudit>
 */
class ProposalAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'actor' => fake()->randomElement(['system', 'user:'.fake()->numberBetween(1, 999)]),
            'event' => fake()->randomElement(ProposalAuditEvent::cases()),
            'payload' => [],
        ];
    }
}
