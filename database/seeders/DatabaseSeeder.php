<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Demo account for the MAAC console (Phase 1). The factory provisions a
        // personal team and sets it as the current team, satisfying the
        // team-scoped console routes. Password: "password".
        User::factory()->create([
            'name' => 'Layla Hassan',
            'email' => 'demo@milaha.com',
        ]);

        // MAAC platform data (Phase 2): reproduces the Phase 1 console fixture
        // as governed database records for the demo team.
        $this->call(MaacDemoSeeder::class);
    }
}
