<?php

use App\Enums\SsoConnectionStatus;
use App\Models\SsoConnection;
use Inertia\Testing\AssertableInertia as Assert;

test('the identity console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('identity', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maacc/identity'));
});

test('a platform admin can register an SSO connection', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Milaha Entra ID',
            'provider' => 'oidc',
            'authorize_url' => 'https://login.microsoftonline.com/authorize',
            'token_url' => 'https://login.microsoftonline.com/token',
            'userinfo_url' => 'https://graph.microsoft.com/oidc/userinfo',
            'client_id' => 'app-client-id',
            'client_secret' => 'super-secret-value',
            'default_team_role' => 'member',
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
        ->and($connection->status)->toBe(SsoConnectionStatus::Active)
        ->and($connection->group_role_mappings)->toBe([['group' => 'MAACC-Admins', 'team_role' => 'admin']]);
});

test('two connections registered with the same name get distinct slugs', function () {
    [$owner, $team] = ownerAndTeam();
    $payload = [
        'name' => 'Shared Name IdP',
        'provider' => 'oidc',
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
        'client_id' => 'cid',
        'client_secret' => 'secret',
        'default_team_role' => 'member',
    ];

    $this->actingAs($owner)->post(route('sso-connections.store', ['current_team' => $team->slug]), $payload)->assertRedirect();
    $this->actingAs($owner)->post(route('sso-connections.store', ['current_team' => $team->slug]), $payload)->assertRedirect();

    $slugs = SsoConnection::where('name', 'Shared Name IdP')->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

test('SSO connection creation validates the endpoints and secret', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Broken',
            'provider' => 'oidc',
            'authorize_url' => 'not-a-url',
            'default_team_role' => 'member',
        ])
        ->assertSessionHasErrors(['authorize_url', 'token_url', 'userinfo_url', 'client_id', 'client_secret']);
});

test('a plain member cannot manage SSO connections', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('sso-connections.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'provider' => 'oidc',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
            'client_id' => 'x',
            'client_secret' => 'y',
            'default_team_role' => 'member',
        ])
        ->assertForbidden();
});

test('updating a connection without a secret preserves the stored one', function () {
    [$owner, $team] = ownerAndTeam();
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
    [$owner, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('sso-connections.update', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]), [
            'status' => 'disabled',
        ])
        ->assertRedirect();

    expect($connection->fresh()->status)->toBe(SsoConnectionStatus::Disabled);

    $this->actingAs($owner)
        ->delete(route('sso-connections.destroy', ['current_team' => $team->slug, 'ssoConnection' => $connection->slug]))
        ->assertRedirect();

    expect($connection->fresh()->trashed())->toBeTrue();
});

test('the console dataset exposes connections without the client secret', function () {
    [$owner, $team] = ownerAndTeam();
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
