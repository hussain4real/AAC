<?php

use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\SsoConnection;
use App\Models\User;
use App\Support\Sso\SsoIdentityPayload;
use App\Support\Sso\SsoUserResolver;
use Database\Seeders\PlatformRbacSeeder;

/**
 * Phase 8B — SSO group claims map onto MAAC platform roles. A tenant user gets
 * no platform role unless a group is explicitly mapped, so platform-admin access
 * is never granted by default.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

it('resolves mapped platform roles from group claims', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maac-platform', 'platform_role' => PlatformRole::SecurityReviewer->value],
        ['group' => 'maac-ops', 'team_role' => 'member'], // no platform_role → ignored
    ])->create();

    expect($connection->resolvePlatformRoles(['maac-platform']))->toBe([PlatformRole::SecurityReviewer])
        ->and($connection->resolvePlatformRoles(['maac-ops']))->toBe([])
        ->and($connection->resolvePlatformRoles(['unknown']))->toBe([]);
});

it('assigns the mapped platform role on SSO login and records a grant', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'sso-admin@corp.com']);

    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maac-platform', 'platform_role' => PlatformRole::SecurityReviewer->value],
    ])->create();

    $payload = new SsoIdentityPayload('ext-platform-1', 'sso-admin@corp.com', 'SSO Admin', ['maac-platform'], []);
    app(SsoUserResolver::class)->resolve($connection, $payload);

    expect($user->fresh()->hasRole(PlatformRole::SecurityReviewer->value))->toBeTrue()
        ->and(PlatformAccessGrant::where('user_id', $user->id)->where('role', PlatformRole::SecurityReviewer->value)->exists())->toBeTrue();
});

it('grants no platform role to a tenant user without a mapped group', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'tenant@corp.com']);

    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maac-platform', 'platform_role' => PlatformRole::Auditor->value],
    ])->create();

    $payload = new SsoIdentityPayload('ext-tenant-1', 'tenant@corp.com', 'Tenant', ['some-other-group'], []);
    app(SsoUserResolver::class)->resolve($connection, $payload);

    expect($user->fresh()->isPlatformAdministrator())->toBeFalse();
});
