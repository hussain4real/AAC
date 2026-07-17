<?php

use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\User;
use App\Support\Sso\SsoIdentityPayload;
use App\Support\Sso\SsoUserResolver;
use Database\Seeders\PlatformRbacSeeder;

/**
 * Phase 8B — SSO group claims map onto MAACC platform roles. A tenant user gets
 * no platform role unless a group is explicitly mapped, so platform-admin access
 * is never granted by default.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

it('does not expose tenant mappings as platform role assignments', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-platform', 'platform_role' => PlatformRole::SecurityReviewer->value],
        ['group' => 'maacc-ops', 'team_role' => 'member'], // no platform_role → ignored
    ])->create();

    expect($connection->resolveTeamRole(['maacc-platform'])->value)->toBe('member');
});

it('never assigns a mapped platform role on tenant SSO login', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-platform', 'platform_role' => PlatformRole::SecurityReviewer->value],
    ])->create();

    $payload = new SsoIdentityPayload('ext-platform-1', 'sso-admin@corp.com', 'SSO Admin', ['maacc-platform'], []);
    $user = app(SsoUserResolver::class)->resolve($connection, $payload);

    expect($user->fresh()->hasRole(PlatformRole::SecurityReviewer->value))->toBeFalse()
        ->and(PlatformAccessGrant::where('user_id', $user->id)->exists())->toBeFalse();
});

it('grants no platform role to a tenant user without a mapped group', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'tenant@corp.com']);

    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-platform', 'platform_role' => PlatformRole::Auditor->value],
    ])->create();
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create(['subject' => 'ext-tenant-1']);

    $payload = new SsoIdentityPayload('ext-tenant-1', 'tenant@corp.com', 'Tenant', ['some-other-group'], []);
    app(SsoUserResolver::class)->resolve($connection, $payload);

    expect($user->fresh()->isPlatformAdministrator())->toBeFalse();
});
