<?php

use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\PlatformRbacSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Phase 8B — the user's MAAC platform access is shared to Inertia so the console
 * can gate platform-admin nav and controls on the real global RBAC.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

it('shares a platform admin\'s roles and permissions', function () {
    $super = User::factory()->create();
    $super->assignRole(PlatformRole::SuperAdmin->value);

    $this->actingAs($super)
        ->get(route('dashboard', ['current_team' => $super->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.platform.isSuperAdmin', true)
            ->where('auth.platform.isAdministrator', true)
            ->where('auth.platform.roles', [PlatformRole::SuperAdmin->value])
            ->has('auth.platform.permissions'));
});

it('shares an empty platform access for a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.platform.roles', [])
            ->where('auth.platform.isSuperAdmin', false)
            ->where('auth.platform.isAdministrator', false));
});

it('shares an empty platform access for a tenant user', function () {
    $tenant = User::factory()->create();

    $this->actingAs($tenant)
        ->get(route('dashboard', ['current_team' => $tenant->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.platform.roles', [])
            ->where('auth.platform.isAdministrator', false));
});
