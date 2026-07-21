<?php

use App\Enums\MaaccRole;
use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Enums\SsoFailureCode;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\PlatformAccessGrant;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\User;
use App\Support\Sso\OidcTokenValidator;
use App\Support\Sso\SsoException;
use App\Support\Sso\SsoSecurityEventRecorder;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\OidcTestProvider;

/**
 * Fake the provider token and userinfo endpoints for the connection's IdP.
 *
 * @param  array<string, mixed>  $userinfo
 */
function fakeIdp(SsoConnection $connection, array $userinfo, int $tokenStatus = 200, int $userinfoStatus = 200, array $claimOverrides = []): void
{
    OidcTestProvider::fake($connection, $userinfo, $tokenStatus, $userinfoStatus, $claimOverrides);
}

/**
 * Perform an SSO callback with a verified state.
 */
function ssoCallback(SsoConnection $connection): TestResponse
{
    return test()->withSession(OidcTestProvider::session($connection))
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'state-token', 'code' => 'auth-code']));
}

function compactOidcToken(string $algorithm = 'RS256'): string
{
    $encode = static fn (array $value): string => rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');

    return $encode(['alg' => $algorithm, 'typ' => 'JWT']).'.'.$encode([]).'.signature';
}

function capturedSsoFailure(callable $callback): SsoException
{
    try {
        $callback();
    } catch (SsoException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected an SSO exception.');
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
        ->toContain('code_challenge_method=S256')
        ->and(session('sso.flows'))->toBeArray()->toHaveCount(1);
});

test('a callback provisions a new user, maps the group to a role, and signs them in', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-admins', 'team_role' => 'admin'],
    ])->create();

    fakeIdp($connection, ['sub' => 'ext-1', 'email' => 'newhire@corp.com', 'name' => 'New Hire', 'groups' => ['maacc-admins']]);

    ssoCallback($connection)->assertRedirect();

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $connection->token_url
        && $request['code_verifier'] === 'code-verifier');

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
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create(['subject' => 'ext-lead']);

    fakeIdp($connection, ['sub' => 'ext-lead', 'email' => 'staff@corp.com', 'name' => 'Staff', 'groups' => ['leads']]);

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
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create(['subject' => 'ext-d2']);

    fakeIdp($connection, ['sub' => 'ext-d2', 'email' => 'dev2@corp.com', 'name' => 'Dev2', 'groups' => ['devs']]);

    ssoCallback($connection)->assertRedirect();

    expect($user->fresh()->maaccRoleFor($project))->toBe(MaaccRole::Developer);
});

test('a callback without a matching group uses the default team role', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'maacc-admins', 'team_role' => 'admin'],
    ])->create(['default_team_role' => TeamRole::Member]);

    fakeIdp($connection, ['sub' => 'ext-2', 'email' => 'member@corp.com', 'name' => 'Member', 'groups' => ['other']]);

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

    fakeIdp($connection, ['sub' => 'ext-dev', 'email' => 'dev@corp.com', 'name' => 'Dev', 'groups' => ['devs']]);

    ssoCallback($connection)->assertRedirect();

    expect(User::firstWhere('email', 'dev@corp.com')->maaccRoleFor($project))->toBe(MaaccRole::Developer);
});

test('a callback never links an unrecognized subject to an existing user by email', function () {
    [, $team] = ownerAndTeam();
    $existing = User::factory()->create(['email' => 'exists@corp.com']);
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp($connection, ['sub' => 'ext-3', 'email' => 'exists@corp.com', 'name' => 'Exists', 'groups' => []]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    expect(User::where('email', 'exists@corp.com')->count())->toBe(1)
        ->and($connection->identities()->where('subject', 'ext-3')->exists())->toBeFalse()
        ->and($team->auditEvents()->where('action', 'sso.login_rejected')->exists())->toBeTrue()
        ->and($existing->fresh()->current_team_id)->not->toBe($team->id);
});

test('a returning identity is recognized and the login is recorded', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'returning@corp.com']);
    $connection = SsoConnection::factory()->for($team)->create();
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create(['subject' => 'ext-4']);

    fakeIdp($connection, ['sub' => 'ext-4', 'email' => 'returning@corp.com', 'name' => 'Returning', 'groups' => []]);

    ssoCallback($connection)->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($connection->identities()->count())->toBe(1)
        ->and($team->auditEvents()->where('action', 'sso.login')->exists())->toBeTrue();
});

test('a callback with a mismatched state is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->withSession(['sso.flows' => ['real' => [
        'state' => 'real',
        'connection_id' => $connection->id,
        'nonce' => 'nonce-token',
        'code_verifier' => 'code-verifier',
        'created_at' => now()->timestamp,
    ]]])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'forged', 'code' => 'c']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

test('a callback without a code is rejected', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    $this->withSession(['sso.flows' => ['state-token' => [
        'state' => 'state-token',
        'connection_id' => $connection->id,
        'nonce' => 'nonce-token',
        'code_verifier' => 'code-verifier',
        'created_at' => now()->timestamp,
    ]]])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'state-token']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');
});

test('an expired SSO transaction is rejected before the IdP exchange', function () {
    config()->set('maacc.sso.flow_ttl_seconds', 300);
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    Http::preventStrayRequests();

    $this->withSession(['sso.flows' => ['expired-state' => [
        'state' => 'expired-state',
        'connection_id' => $connection->id,
        'nonce' => 'nonce-token',
        'code_verifier' => 'code-verifier',
        'created_at' => now()->subMinutes(10)->timestamp,
    ]]])
        ->get(route('sso.callback', [
            'ssoConnection' => $connection->slug,
            'state' => 'expired-state',
            'code' => 'auth-code',
        ]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    expect($team->auditEvents()->where('action', 'sso.login_rejected')->firstOrFail()->metadata['failure_code'])
        ->toBe('flow_expired');
});

test('an incomplete SSO transaction is rejected before the IdP exchange', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    Http::preventStrayRequests();

    $this->withSession(['sso.flows' => ['incomplete-state' => [
        'state' => 'incomplete-state',
        'connection_id' => $connection->id,
        'created_at' => now()->timestamp,
    ]]])
        ->get(route('sso.callback', [
            'ssoConnection' => $connection->slug,
            'state' => 'incomplete-state',
            'code' => 'auth-code',
        ]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    expect($team->auditEvents()->where('action', 'sso.login_rejected')->firstOrFail()->metadata['failure_code'])
        ->toBe('flow_expired');
});

test('a token exchange failure surfaces a controlled login error', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp($connection, [], tokenStatus: 401);

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

    fakeIdp($connection, ['name' => 'No Subject']);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});

test('a connection that does not auto-provision rejects an unknown identity', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create(['auto_provision' => false]);

    fakeIdp($connection, ['sub' => 'ext-5', 'email' => 'stranger@corp.com', 'name' => 'Stranger', 'groups' => []]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    expect(User::where('email', 'stranger@corp.com')->exists())->toBeFalse();
});

test('a disabled connection is not reachable', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->disabled()->create();

    $this->get(route('sso.redirect', ['ssoConnection' => $connection->slug]))->assertNotFound();
    $this->get(route('sso.callback', ['ssoConnection' => $connection->slug]))->assertNotFound();
});

test('the login screen lists only the requested tenants active SSO connections', function () {
    [, $team] = ownerAndTeam();
    SsoConnection::factory()->for($team)->create(['name' => 'Corp IdP']);
    SsoConnection::factory()->for($team)->disabled()->create();
    [, $otherTeam] = ownerAndTeam();
    SsoConnection::factory()->for($otherTeam)->create(['name' => 'Other IdP']);

    $this->withoutVite()
        ->get(route('login', ['team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->has('ssoConnections', 1)
            ->where('ssoConnections.0.name', 'Corp IdP'));

    $this->withoutVite()
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('ssoConnections', 0));
});

test('a callback rejects wrong issuer audience nonce and unverified email claims', function (array $overrides) {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    fakeIdp($connection, ['sub' => 'bad-claims', 'email' => 'claims@corp.com', 'groups' => []], claimOverrides: $overrides);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect(User::where('email', 'claims@corp.com')->exists())->toBeFalse();
})->with([
    'wrong issuer' => [['iss' => 'https://attacker.example.com']],
    'wrong audience' => [['aud' => 'other-client', 'azp' => 'other-client']],
    'wrong nonce' => [['nonce' => 'other-nonce']],
    'unverified email' => [['email_verified' => false]],
]);

test('a callback rejects authorized-party and lifetime claim failures', function (array $overrides, SsoFailureCode $failureCode) {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $resolvedOverrides = $overrides;

    if (($resolvedOverrides['aud'] ?? null) === 'multiple') {
        $resolvedOverrides['aud'] = [$connection->client_id, 'other-audience'];
    }

    fakeIdp(
        $connection,
        ['sub' => 'claims-failure', 'email' => 'claims-failure@corp.com', 'groups' => []],
        claimOverrides: $resolvedOverrides,
    );

    ssoCallback($connection)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    expect($team->auditEvents()->where('action', 'sso.login_rejected')->firstOrFail()->metadata['failure_code'])
        ->toBe($failureCode->value);
})->with([
    'authorized party mismatch' => [['aud' => 'multiple', 'azp' => 'wrong-client'], SsoFailureCode::AuthorizedPartyMismatch],
    'lifetime claims missing' => [['exp' => null], SsoFailureCode::LifetimeInvalid],
]);

test('a callback rejects auto provisioning outside the approved tenant domains', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create(['allowed_domains' => ['corp.com']]);

    fakeIdp($connection, ['sub' => 'wrong-domain', 'email' => 'user@outside.example', 'groups' => []]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    expect(User::where('email', 'user@outside.example')->exists())->toBeFalse();
});

test('a callback rejects an unsigned ID token', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $encode = static fn (array $value): string => rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');
    $unsignedToken = $encode(['alg' => 'none']).'.'.$encode([
        'iss' => $connection->issuer,
        'aud' => $connection->client_id,
        'sub' => 'unsigned-subject',
        'nonce' => 'nonce-token',
        'iat' => now()->timestamp,
        'exp' => now()->addMinutes(5)->timestamp,
        'email' => 'unsigned@corp.com',
        'email_verified' => true,
    ]).'.';

    Http::fake([
        $connection->token_url => Http::response(['access_token' => 'access', 'id_token' => $unsignedToken]),
    ]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect(User::where('email', 'unsigned@corp.com')->exists())->toBeFalse();
});

test('the token validator rejects malformed tokens and unapproved algorithms', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $validator = app(OidcTokenValidator::class);

    expect(capturedSsoFailure(fn () => $validator->validate($connection, 'only.two', 'nonce'))->failureCode)
        ->toBe(SsoFailureCode::InvalidOrUnsignedToken)
        ->and(capturedSsoFailure(fn () => $validator->validate($connection, compactOidcToken('HS256'), 'nonce'))->failureCode)
        ->toBe(SsoFailureCode::InvalidAlgorithm);
});

test('the token validator requires a pinned signing key endpoint', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create(['jwks_url' => null]);

    expect(capturedSsoFailure(
        fn () => app(OidcTokenValidator::class)->validate($connection, compactOidcToken(), 'nonce'),
    )->failureCode)->toBe(SsoFailureCode::JwksUnavailable);
});

test('the token validator reports signing key connection outages', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    Http::fake(fn () => throw new ConnectionException('JWKS offline'));

    expect(capturedSsoFailure(
        fn () => app(OidcTokenValidator::class)->validate($connection, compactOidcToken(), 'nonce'),
    )->failureCode)->toBe(SsoFailureCode::JwksUnavailable);
});

test('the token validator rejects failed and invalid signing key responses', function (mixed $response, SsoFailureCode $failureCode) {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    Http::fake([$connection->jwks_url => $response]);

    expect(capturedSsoFailure(
        fn () => app(OidcTokenValidator::class)->validate($connection, compactOidcToken(), 'nonce'),
    )->failureCode)->toBe($failureCode);
})->with([
    'failed endpoint' => [fn () => Http::response([], 503), SsoFailureCode::JwksUnavailable],
    'non-object key set' => [fn () => Http::response('invalid'), SsoFailureCode::InvalidSigningKeys],
    'empty key set' => [fn () => Http::response(['keys' => []]), SsoFailureCode::InvalidSigningKeys],
]);

test('the token validator rejects a corrupted signed token', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $fixture = OidcTestProvider::signedFixture($connection, [
        'sub' => 'corrupted-subject',
        'email' => 'corrupted@corp.com',
    ]);
    $segments = explode('.', $fixture['id_token']);
    $segments[2] = 'corrupted-signature';
    Http::fake([$connection->jwks_url => Http::response($fixture['key_set'])]);

    expect(capturedSsoFailure(
        fn () => app(OidcTokenValidator::class)->validate($connection, implode('.', $segments), 'nonce-token'),
    )->failureCode)->toBe(SsoFailureCode::InvalidOrUnsignedToken);
});

test('connection key-set tests surface every unavailable or invalid endpoint state', function (string $state, SsoFailureCode $failureCode) {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create([
        'jwks_url' => $state === 'missing' ? null : 'https://idp.example.com/custom-jwks',
    ]);

    if ($state === 'outage') {
        Http::fake(fn () => throw new ConnectionException('JWKS offline'));
    } elseif ($state === 'failed') {
        Http::fake([$connection->jwks_url => Http::response([], 503)]);
    } elseif ($state === 'empty') {
        Http::fake([$connection->jwks_url => Http::response(['keys' => []])]);
    }

    expect(capturedSsoFailure(
        fn () => app(OidcTokenValidator::class)->testKeySet($connection),
    )->failureCode)->toBe($failureCode);
})->with([
    'missing endpoint' => ['missing', SsoFailureCode::JwksUnavailable],
    'connection outage' => ['outage', SsoFailureCode::JwksUnavailable],
    'failed endpoint' => ['failed', SsoFailureCode::InvalidSigningKeys],
    'empty keys' => ['empty', SsoFailureCode::InvalidSigningKeys],
]);

test('userinfo failures preserve stable security failure codes', function (string $state, SsoFailureCode $failureCode) {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $userinfo = ['sub' => 'userinfo-subject', 'email' => 'userinfo@corp.com', 'groups' => []];
    $fixture = OidcTestProvider::signedFixture($connection, $userinfo);

    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use ($connection, $fixture, $state, $userinfo) {
        return match ($request->url()) {
            $connection->token_url => Http::response([
                'access_token' => 'userinfo-access-token',
                'id_token' => $fixture['id_token'],
            ]),
            $connection->jwks_url => Http::response($fixture['key_set']),
            $connection->userinfo_url => match ($state) {
                'outage' => throw new ConnectionException('Userinfo offline'),
                'rejected' => Http::response([], 503),
                'invalid' => Http::response('"not-an-object"'),
                'mismatch' => Http::response([...$userinfo, 'sub' => 'different-subject']),
            },
        };
    });

    ssoCallback($connection)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    expect($team->auditEvents()->where('action', $failureCode->auditAction())->firstOrFail()->metadata['failure_code'])
        ->toBe($failureCode->value);
})->with([
    'userinfo outage' => ['outage', SsoFailureCode::IdpUnavailable],
    'userinfo rejected' => ['rejected', SsoFailureCode::UserinfoRejected],
    'userinfo invalid' => ['invalid', SsoFailureCode::InvalidUserinfo],
    'userinfo subject mismatch' => ['mismatch', SsoFailureCode::SubjectMismatch],
]);

test('security event recording preserves an existing correlation id', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();
    $request = Request::create('/sso/callback');
    $request->attributes->set('correlation_id', 'corr_existing_sso');

    $correlationId = app(SsoSecurityEventRecorder::class)->record(
        $connection,
        $request,
        SsoFailureCode::AuthorizationCodeMissing,
    );

    expect($correlationId)->toBe('corr_existing_sso')
        ->and($request->attributes->get('correlation_id'))->toBe('corr_existing_sso')
        ->and($team->auditEvents()
            ->where('action', SsoFailureCode::AuthorizationCodeMissing->auditAction())
            ->latest('sequence')
            ->firstOrFail()
            ->metadata['correlation_id'])
        ->toBe('corr_existing_sso');
});

test('a returning SSO identity rejects an unexpected email change', function () {
    [, $team] = ownerAndTeam();
    $user = User::factory()->create(['email' => 'original@corp.com']);
    $connection = SsoConnection::factory()->for($team)->create();
    $identity = SsoIdentity::factory()->for($connection, 'connection')->for($user)->create([
        'subject' => 'stable-subject',
        'email' => 'original@corp.com',
    ]);

    fakeIdp($connection, [
        'sub' => 'stable-subject',
        'email' => 'changed@corp.com',
        'groups' => [],
    ]);

    ssoCallback($connection)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect($user->fresh()->email)->toBe('original@corp.com')
        ->and($identity->fresh()->email)->toBe('original@corp.com')
        ->and($team->auditEvents()->where('action', 'sso.login_rejected')->firstOrFail()->metadata['failure_code'])
        ->toBe(SsoFailureCode::IdentityEmailMismatch->value);
});

test('SSO entry points apply a dedicated per-connection throttle', function () {
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    foreach (range(1, 10) as $attempt) {
        $this->get(route('sso.redirect', ['ssoConnection' => $connection->slug]))->assertRedirect();
    }

    $this->get(route('sso.redirect', ['ssoConnection' => $connection->slug]))->assertTooManyRequests();
});

test('repeated rejected SSO transactions create one durable anomaly signal', function () {
    config()->set('maacc.sso.rejected_login_alert_threshold', 3);
    [, $team] = ownerAndTeam();
    $connection = SsoConnection::factory()->for($team)->create();

    foreach (range(1, 4) as $attempt) {
        $this->withSession(['sso.flows' => ['expected' => [
            'state' => 'expected',
            'connection_id' => $connection->id,
            'nonce' => 'nonce-token',
            'code_verifier' => 'code-verifier',
            'created_at' => now()->timestamp,
        ]]])
            ->get(route('sso.callback', [
                'ssoConnection' => $connection->slug,
                'state' => 'forged-'.$attempt,
                'code' => 'auth-code',
            ]))
            ->assertRedirect(route('login'));
    }

    $anomaly = $team->auditEvents()->where('action', 'sso.anomaly.detected')->firstOrFail();

    expect($team->auditEvents()->where('action', 'sso.login_rejected')->count())->toBe(4)
        ->and($team->auditEvents()->where('action', 'sso.anomaly.detected')->count())->toBe(1)
        ->and($anomaly->metadata['failure_code'])->toBe('state_or_connection_mismatch')
        ->and($anomaly->metadata['recent_failures'])->toBe(3);
});

test('an IdP outage preserves local Super Admin login and audited break-glass recovery', function () {
    $this->seed(PlatformRbacSeeder::class);
    [$superAdmin, $team] = ownerAndTeam();
    $superAdmin->assignRole(PlatformRole::SuperAdmin->value);
    $operator = teamMember($team);
    $connection = SsoConnection::factory()->for($team)->create();

    Http::fake(fn () => throw new ConnectionException('Identity provider offline.'));

    ssoCallback($connection)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect($team->auditEvents()->where('action', 'sso.idp_unavailable')->exists())->toBeTrue();

    $this->post(route('login'), [
        'email' => $superAdmin->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($superAdmin);

    $this->post(route('access-control.grants.store', ['current_team' => $team->slug]), [
        'user_id' => $operator->id,
        'role' => PlatformRole::PlatformAdmin->value,
        'kind' => PlatformAccessKind::BreakGlass->value,
        'reason' => 'Recover access while the tenant IdP is unavailable',
        'ttl_minutes' => 30,
    ])->assertRedirect();

    $grant = PlatformAccessGrant::query()->where('user_id', $operator->id)->firstOrFail();

    expect($grant->isBreakGlass())->toBeTrue()
        ->and($grant->expires_at)->not->toBeNull()
        ->and($team->auditEvents()->where('action', 'platform_access.break_glass_activated')->exists())->toBeTrue();
});

test('a tenant identity cannot authenticate an existing platform administrator', function () {
    $this->seed(PlatformRbacSeeder::class);
    [, $team] = ownerAndTeam();
    $administrator = User::factory()->create(['email' => 'global-admin@corp.com']);
    $administrator->assignRole(PlatformRole::PlatformAdmin->value);
    $connection = SsoConnection::factory()->for($team)->create();
    SsoIdentity::factory()->for($connection, 'connection')->for($administrator)->create(['subject' => 'privileged-subject']);

    fakeIdp($connection, ['sub' => 'privileged-subject', 'email' => 'global-admin@corp.com', 'groups' => []]);

    ssoCallback($connection)->assertRedirect(route('login'))->assertSessionHasErrors('sso');

    $this->assertGuest();
    expect($administrator->fresh()->current_team_id)->not->toBe($team->id);
});

test('a callback rejects connection mixup and consumes a successful state exactly once', function () {
    [, $team] = ownerAndTeam();
    $first = SsoConnection::factory()->for($team)->create();
    $second = SsoConnection::factory()->for($team)->create();
    $flow = ['state' => 'one-use', 'connection_id' => $first->id, 'nonce' => 'nonce-token', 'code_verifier' => 'code-verifier', 'created_at' => now()->timestamp];

    $this->withSession(['sso.flows' => ['one-use' => $flow]])
        ->get(route('sso.callback', ['ssoConnection' => $second->slug, 'state' => 'one-use', 'code' => 'auth-code']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    fakeIdp($first, ['sub' => 'one-use-subject', 'email' => 'one-use@corp.com', 'groups' => []]);

    $this->withSession(['sso.flows' => ['one-use' => $flow]])
        ->get(route('sso.callback', ['ssoConnection' => $first->slug, 'state' => 'one-use', 'code' => 'auth-code']))
        ->assertRedirect();

    $this->get(route('sso.callback', ['ssoConnection' => $first->slug, 'state' => 'one-use', 'code' => 'auth-code']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    expect($first->identities()->where('subject', 'one-use-subject')->count())->toBe(1);
});

test('removing an IdP project mapping revokes the project membership managed by that connection', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $user = User::factory()->create(['email' => 'former-dev@corp.com']);
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $project->members()->attach($user, ['maacc_role' => MaaccRole::Developer->value]);
    $connection = SsoConnection::factory()->for($team)->create();
    SsoIdentity::factory()->for($connection, 'connection')->for($user)->create([
        'subject' => 'former-dev-subject',
        'managed_project_ids' => [$project->id],
    ]);

    fakeIdp($connection, ['sub' => 'former-dev-subject', 'email' => 'former-dev@corp.com', 'groups' => []]);

    ssoCallback($connection)->assertRedirect();

    expect($user->fresh()->maaccRoleFor($project))->toBeNull();
});
