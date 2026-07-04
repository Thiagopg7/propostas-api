<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Proposal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Client::factory(10)
            ->has(Proposal::factory()->count(3))
            ->create();
    }
}
