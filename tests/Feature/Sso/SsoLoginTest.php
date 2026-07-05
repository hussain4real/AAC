<?php

use App\Enums\MaaccRole;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Fake the provider token and userinfo endpoints for the connection's IdP.
 *
 * @param  array<string, mixed>  $userinfo
 */
function fakeIdp(array $userinfo, int $tokenStatus = 200, int $userinfoStatus = 200): void
{
    Http::preventStrayRequests();
    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'at-123', 'token_type' => 'Bearer'], $tokenStatus),
        'idp.example.com/userinfo' => Http::response($userinfo, $userinfoStatus),
    ]);
}

/**
 * Perform an SSO callback with a verified state.
 */
function ssoCallback(SsoConnection $connection): TestResponse
{
    return test()->withSession(['sso.state' => 'state-token'])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'state-token', 'code' => 'auth-code']));
}

test('the redirect builds the provider authorize url and stores the state', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $location = $this->get(route('sso.redirect', ['ssoConnection' => $connection->slug]))
        ->assertStatus(302)
        ->headers->get('Location');

    expect($location)->toContain($connection->authorize_url)
        ->toContain('client_id='.urlencode($connection->client_id))
        ->toContain('state=')
        ->and(session('sso.state'))->toBeString();
});

test('a callback provisions a new user, maps the group to a role, and signs them in', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-admins', 'team_role' => 'admin'],
    ])->create();

    fakeIdp(['sub' => 'ext-1', 'email' => 'newhire@corp.com', 'name' => 'New Hire', 'groups' => ['maacc-admins']]);

    ssoCallback($connection)->assertRedirect();

    $user = User::firstWhere('email', 'newhire@corp.com');

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('New Hire')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($connection->identities()->where('subject', 'ext-1')->exists())->toBeTrue()
        ->and($user->teamRole($team))->toBe(TeamRole::Admin)
        ->and($user->current_team_id)->toBe($team->id)
        ->and($team->auditEvents()->where('action', 'sso.provisioned')->exists())->toBeTrue();
});

test('a callback re-applies the mapped role to an existing team member', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'staff@corp.com']);
    $team->members()->attach($user, ['role' => 'member']);
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'leads', 'team_role' => 'admin'],
    ])->create();

    fakeIdp(['sub' => 'ext-lead', 'email' => 'staff@corp.com', 'name' => 'Staff', 'groups' => ['leads']]);

    ssoCallback($connection)->assertRedirect();

    expect($user->fresh()->teamRole($team))->toBe(TeamRole::Admin)
        ->and($user->ssoIdentities()->count())->toBe(1);
});

test('a callback updates an existing project role and ignores unknown projects', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $user = User::factory()->create(['email' => 'dev2@corp.com']);
    $team->members()->attach($user, ['role' => 'member']);
    $project->members()->attach($user, ['maacc_role' => MaaccRole::Viewer->value]);

    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'devs', 'team_role' => 'member', 'maacc_role' => 'developer', 'project_slug' => $project->slug],
        ['group' => 'devs', 'team_role' => 'member', 'maacc_role' => 'auditor', 'project_slug' => 'ghost-project'],
    ])->create();

    fakeIdp(['sub' => 'ext-d2', 'email' => 'dev2@corp.com', 'name' => 'Dev2', 'groups' => ['devs']]);

    ssoCallback($connection)->assertRedirect();

    expect($user->fresh()->maaccRoleFor($project))->toBe(MaaccRole::Developer);
});

test('a callback without a matching group uses the default team role', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-admins', 'team_role' => 'admin'],
    ])->create(['default_team_role' => TeamRole::Member]);

    fakeIdp(['sub' => 'ext-2', 'email' => 'member@corp.com', 'name' => 'Member', 'groups' => ['other']]);

    ssoCallback($connection)->assertRedirect();

    expect(User::firstWhere('email', 'member@corp.com')->teamRole($team))->toBe(TeamRole::Member);
});

test('a callback maps a group to a project MAACC role', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'devs', 'team_role' => 'member', 'maacc_role' => 'developer', 'project_slug' => $project->slug],
    ])->create();

    fakeIdp(['sub' => 'ext-dev', 'email' => 'dev@corp.com', 'name' => 'Dev', 'groups' => ['devs']]);

    ssoCallback($connection)->assertRedirect();

    expect(User::firstWhere('email', 'dev@corp.com')->maaccRoleFor($project))->toBe(MaaccRole::Developer);
});

test('a callback links an existing user by email instead of creating a new one', function () {
    [, $team] = ownerAndTeam();
    $existing = User::factory()->create(['email' => 'exists@corp.com']);
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp(['sub' => 'ext-3', 'email' => 'exists@corp.com', 'name' => 'Exists', 'groups' => []]);

    ssoCallback($connection)->assertRedirect();

    expect(User::where('email', 'exists@corp.com')->count())->toBe(1)
        ->and($connection->identities()->where('subject', 'ext-3')->first()->user_id)->toBe($existing->id)
        ->and($team->auditEvents()->where('action', 'sso.login')->exists())->toBeTrue();
});

test('a returning identity is recognized and the login is recorded', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'returning@corp.com']);
    $connection = SsoConnection::factory()->for($team)->create();
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create(['subject' => 'ext-4']);

    fakeIdp(['sub' => 'ext-4', 'email' => 'returning@corp.com', 'name' => 'Returning', 'groups' => []]);

    ssoCallback($connection)->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($connection->identities()->count())->toBe(1)
        ->and($team->auditEvents()->where('action', 'sso.login')->exists())->toBeTrue();
});

test('a callback with a mismatched state is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->withSession(['sso.state' => 'real'])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'forged', 'code' => 'c']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

test('a callback without a code is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->withSession(['sso.state' => 'state-token'])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'state-token']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');
});

test('a token exchange failure surfaces a controlled login error', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp([], tokenStatus: 401);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
    $this->assertGuest();
});

test('a token response without an access token is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    Http::preventStrayRequests();
    Http::fake([
        'idp.example.com/token' => Http::response(['token_type' => 'Bearer']),
        'idp.example.com/userinfo' => Http::response([]),
    ]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});

test('a failed userinfo request is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    Http::preventStrayRequests();
    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'at-123']),
        'idp.example.com/userinfo' => Http::response('', 500),
    ]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});

test('an invalid (non-object) userinfo payload is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    Http::preventStrayRequests();
    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'at-123']),
        'idp.example.com/userinfo' => Http::response('"not-an-object"'),
    ]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});

test('a userinfo response missing identity claims is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp(['name' => 'No Subject']);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});

test('a connection that does not auto-provision rejects an unknown identity', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create(['auto_provision' => false]);

    fakeIdp(['sub' => 'ext-5', 'email' => 'stranger@corp.com', 'name' => 'Stranger', 'groups' => []]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    expect(User::where('email', 'stranger@corp.com')->exists())->toBeFalse();
});

test('a disabled connection is not reachable', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->disabled()->create();

    $this->get(route('sso.redirect', ['ssoConnection' => $connection->slug]))->assertNotFound();
    $this->get(route('sso.callback', ['ssoConnection' => $connection->slug]))->assertNotFound();
});

test('the login screen lists the active SSO connections', function () {
    [, $team] = ownerAndTeam();
    SsoConnection::factory()->for($team)->create(['name' => 'Corp IdP']);
    SsoConnection::factory()->for($team)->disabled()->create();

    $this->withoutVite()
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->has('ssoConnections', 1)
            ->where('ssoConnections.0.name', 'Corp IdP'));
});
