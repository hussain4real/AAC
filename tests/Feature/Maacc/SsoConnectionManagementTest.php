<?php

use App\Enums\PlatformRole;
use App\Enums\SsoConnectionStatus;
use App\Models\SsoConnection;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

/**
 * @return array{0: User, 1: Team}
 */
function identityManagerAndTeam(): array
{
    [$owner, $team] = ownerAndTeam();
    $owner->assignRole(PlatformRole::PlatformAdmin->value);

    return [$owner, $team];
}

test('the identity console page renders', function () {
    [$owner, $team] = identityManagerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('identity', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maacc/identity'));
});

test('a platform admin can register an SSO connection', function () {
    [$owner, $team] = identityManagerAndTeam();

    $this->actingAs($owner)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Milaha Entra ID',
            'provider' => 'oidc',
            'issuer' => 'https://login.microsoftonline.com/tenant/v2.0',
            'authorize_url' => 'https://login.microsoftonline.com/authorize',
            'token_url' => 'https://login.microsoftonline.com/token',
            'userinfo_url' => 'https://graph.microsoft.com/oidc/userinfo',
            'jwks_url' => 'https://login.microsoftonline.com/keys',
            'client_id' => 'app-client-id',
            'client_secret' => 'super-secret-value',
            'default_team_role' => 'member',
            'allowed_domains' => ['corp.com'],
            'group_role_mappings' => [
                ['group' => 'MAACC-Admins', 'team_role' => 'admin'],
            ],
        ])
        ->assertRedirect();

    $connection = SsoConnection::firstWhere('name', 'Milaha Entra ID');

    expect($connection)->not->toBeNull()
        ->and($connection->team_id)->toBe($team->id)
        ->and($connection->client_secret)->toBe('super-secret-value')
        ->and($connection->getRawOriginal('client_secret'))->not->toBe('super-secret-value')
        ->and($connection->status)->toBe(SsoConnectionStatus::Draft)
        ->and($connection->group_role_mappings)->toBe([['group' => 'MAACC-Admins', 'team_role' => 'admin']]);
});

test('two connections registered with the same name get distinct slugs', function () {
    [$owner, $team] = identityManagerAndTeam();
    $payload = [
        'name' => 'Shared Name IdP',
        'provider' => 'oidc',
        'issuer' => 'https://idp.example.com',
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
        'jwks_url' => 'https://idp.example.com/jwks',
        'client_id' => 'cid',
        'client_secret' => 'secret',
        'default_team_role' => 'member',
        'allowed_domains' => ['corp.com'],
    ];

    $this->actingAs($owner)->post(route('sso-connections.store', ['current_team' => $team->slug]), $payload)->assertRedirect();
    $this->actingAs($owner)->post(route('sso-connections.store', ['current_team' => $team->slug]), $payload)->assertRedirect();

    $slugs = SsoConnection::where('name', 'Shared Name IdP')->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

test('SSO connection creation validates the endpoints and secret', function () {
    [$owner, $team] = identityManagerAndTeam();

    $this->actingAs($owner)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Broken',
            'provider' => 'oidc',
            'authorize_url' => 'not-a-url',
            'default_team_role' => 'member',
        ])
        ->assertSessionHasErrors(['issuer', 'authorize_url', 'token_url', 'userinfo_url', 'jwks_url', 'client_id', 'client_secret']);
});

test('a plain member cannot manage SSO connections', function () {
    [, $team] = identityManagerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'provider' => 'oidc',
            'issuer' => 'https://idp.example.com',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
            'jwks_url' => 'https://idp.example.com/jwks',
            'client_id' => 'x',
            'client_secret' => 'y',
            'default_team_role' => 'member',
            'allowed_domains' => ['corp.com'],
        ])
        ->assertForbidden();
});

test('a tenant owner without a global identity permission cannot manage SSO connections', function () {
    [$tenantOwner, $team] = ownerAndTeam();

    $this->actingAs($tenantOwner)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Tenant-controlled IdP',
            'provider' => 'oidc',
            'issuer' => 'https://idp.example.com',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
            'jwks_url' => 'https://idp.example.com/jwks',
            'client_id' => 'x',
            'client_secret' => 'y',
            'default_team_role' => 'member',
            'allowed_domains' => ['corp.com'],
        ])
        ->assertForbidden();
});

test('a connection requires a successful test and independent security approval before activation', function () {
    [$manager, $team] = identityManagerAndTeam();
    $reviewer = teamMember($team);
    $reviewer->assignRole(PlatformRole::SecurityReviewer->value);
    $connection = SsoConnection::factory()->for($team)->draft()->create(['created_by' => $manager->id]);

    Http::fake([$connection->jwks_url => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'key-1']]])]);

    $this->actingAs($manager)
        ->post(route('sso-connections.test', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect();

    expect($connection->fresh()->status)->toBe(SsoConnectionStatus::PendingApproval)
        ->and($connection->fresh()->tested_at)->not->toBeNull();

    $this->actingAs($manager)
        ->post(route('sso-connections.approve', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertUnprocessable();

    $this->actingAs($reviewer)
        ->post(route('sso-connections.approve', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect();

    $fresh = $connection->fresh();
    expect($fresh->status)->toBe(SsoConnectionStatus::Active)
        ->and($fresh->approved_by)->toBe($reviewer->id)
        ->and($team->auditEvents()->where('action', 'sso.connection_tested')->exists())->toBeTrue()
        ->and($team->auditEvents()->where('action', 'sso.connection_approved')->exists())->toBeTrue();
});

test('a failed connection test remains draft and records the controlled failure', function () {
    [$manager, $team] = identityManagerAndTeam();
    $connection = SsoConnection::factory()->for($team)->draft()->create(['created_by' => $manager->id]);
    Http::fake([$connection->jwks_url => Http::response([], 503)]);

    $this->actingAs($manager)
        ->post(route('sso-connections.test', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect()
        ->assertSessionHasErrors('sso');

    expect($connection->fresh()->status)->toBe(SsoConnectionStatus::Draft)
        ->and($team->auditEvents()->where('action', 'sso.connection_test_failed')->exists())->toBeTrue();
});

test('changing an approved connection returns it to draft and clears approval evidence', function () {
    [$manager, $team] = identityManagerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->actingAs($manager)
        ->put(route('sso-connections.update', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]), [
            'name' => 'Changed IdP',
        ])
        ->assertRedirect();

    $fresh = $connection->fresh();
    expect($fresh->status)->toBe(SsoConnectionStatus::Draft)
        ->and($fresh->tested_at)->toBeNull()
        ->and($fresh->approved_at)->toBeNull()
        ->and($fresh->approved_by)->toBeNull();
});

test('SSO endpoint validation rejects insecure and private destinations', function () {
    [$manager, $team] = identityManagerAndTeam();

    $this->actingAs($manager)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Unsafe IdP',
            'provider' => 'oidc',
            'issuer' => 'http://idp.example.com',
            'authorize_url' => 'https://127.0.0.1/authorize',
            'token_url' => 'https://169.254.169.254/token',
            'userinfo_url' => 'https://localhost/userinfo',
            'jwks_url' => 'https://metadata.google.internal/keys',
            'client_id' => 'x',
            'client_secret' => 'y',
            'default_team_role' => 'member',
            'allowed_domains' => ['corp.com'],
        ])
        ->assertSessionHasErrors(['issuer', 'authorize_url', 'token_url', 'userinfo_url', 'jwks_url']);
});

test('updating a connection without a secret preserves the stored one', function () {
    [$owner, $team] = identityManagerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create(['client_secret' => 'original-secret']);

    $this->actingAs($owner)
        ->put(route('sso-connections.update', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]), [
            'name' => 'Renamed IdP',
            'client_secret' => '',
        ])
        ->assertRedirect();

    $fresh = $connection->fresh();
    expect($fresh->name)->toBe('Renamed IdP')
        ->and($fresh->client_secret)->toBe('original-secret');
});

test('a connection can be disabled and deleted', function () {
    [$owner, $team] = identityManagerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('sso-connections.disable', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect();

    expect($connection->fresh()->status)->toBe(SsoConnectionStatus::Disabled);

    $this->actingAs($owner)
        ->delete(route('sso-connections.destroy', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect();

    expect($connection->fresh()->trashed())->toBeTrue();
});

test('the console dataset exposes connections without the client secret', function () {
    [$owner, $team] = identityManagerAndTeam();
    SsoConnection::factory()->for($team)->create(['name' => 'Corp IdP', 'client_secret' => 'hidden']);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.ssoConnections', 1)
            ->where('maacc.ssoConnections.0.name', 'Corp IdP')
            ->where('maacc.ssoConnections.0.secretConfigured', true)
            ->missing('maacc.ssoConnections.0.client_secret')
            ->missing('maacc.ssoConnections.0.clientSecret'));
});
