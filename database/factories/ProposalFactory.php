<?php

namespace Database\Factories;

use App\Enums\ProposalOrigin;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'product' => fake()->words(2, true),
            'monthly_value' => fake()->randomFloat(2, 50, 10000),
            'status' => ProposalStatus::Draft,
            'origin' => fake()->randomElement(ProposalOrigin::cases()),
            'version' => 1,
        ];
    }

    public function status(ProposalStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function submitted(): static
    {
        return $this->status(ProposalStatus::Submitted);
    }

    public function approved(): static
    {
        return $this->status(ProposalStatus::Approved);
    }

    public function rejected(): static
    {
        return $this->status(ProposalStatus::Rejected);
    }

    public function canceled(): static
    {
        return $this->status(ProposalStatus::Canceled);
    }
}
