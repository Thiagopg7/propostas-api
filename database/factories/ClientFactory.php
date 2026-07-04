<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'document' => fake('pt_BR')->unique()->cpf(false),
        ];
    }

    public function withCnpj(): static
    {
        return $this->state(fn (array $attributes): array => [
            'document' => fake('pt_BR')->unique()->cnpj(false),
        ]);
    }
}
