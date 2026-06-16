<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Policies\TeamPolicy;
use App\Providers\AppServiceProvider;
use App\Rules\TeamName;
use App\Rules\ValidTeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\HttpFoundation\Response;

test('team helpers cover unique slugs relationships roles and failed switching', function () {
    Team::factory()->create(['name' => 'Acme Team', 'slug' => 'acme-team']);

    $team = Team::factory()->create(['name' => 'Acme', 'slug' => '']);

    expect($team->slug)->toBe('acme-1');

    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create(['name' => 'Owned Team']);
    $adminTeam = Team::factory()->create(['name' => 'Admin Team']);
    $externalTeam = Team::factory()->create(['name' => 'External Team']);

    $ownedTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $adminTeam->members()->attach($user, ['role' => TeamRole::Admin->value]);

    $membership = $user->teamMemberships()
        ->where('team_id', $ownedTeam->id)
        ->firstOrFail();

    expect($user->ownedTeams()->pluck('teams.id')->all())
        ->toContain($ownedTeam->id)
        ->not->toContain($adminTeam->id)
        ->and($membership->team->is($ownedTeam))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($user->switchTeam($externalTeam))->toBeFalse()
        ->and(TeamRole::Owner->isAtLeast(TeamRole::Member))->toBeTrue()
        ->and(TeamRole::Admin->isAtLeast(TeamRole::Member))->toBeTrue()
        ->and(TeamRole::Member->isAtLeast(TeamRole::Owner))->toBeFalse()
        ->and(TeamRole::assignable())->toBe([
            ['value' => TeamRole::Admin->value, 'label' => TeamRole::Admin->label()],
            ['value' => TeamRole::Member->value, 'label' => TeamRole::Member->label()],
        ]);
});

test('team membership middleware switches current team and enforces minimum roles', function () {
    Route::get('/coverage/{current_team}/switch', fn (): Response => response('ok'))
        ->middleware(['web', 'auth', EnsureTeamMembership::class])
        ->name('coverage.team.switch');

    Route::get('/coverage/team/{team}/admin', fn (): Response => response('ok'))
        ->middleware(['web', 'auth', EnsureTeamMembership::class.':admin'])
        ->name('coverage.team.admin');

    app('router')->getRoutes()->refreshNameLookups();

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->fresh()->isCurrentTeam($team))->toBeFalse();

    $this
        ->actingAs($user)
        ->get(route('coverage.team.switch', ['current_team' => $team->slug]))
        ->assertOk();

    expect($user->fresh()->isCurrentTeam($team))->toBeTrue();

    $this
        ->actingAs($user)
        ->get(route('coverage.team.admin', ['team' => $team->slug]))
        ->assertForbidden();
});

test('fortify responses support json variants and two factor redirects', function () {
    $jsonRequest = Request::create('/login', 'POST', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    expect((new LoginResponse)->toResponse($jsonRequest)->getData(true))
        ->toBe(['two_factor' => false])
        ->and((new RegisterResponse)->toResponse($jsonRequest)->getStatusCode())->toBe(201)
        ->and((new TwoFactorLoginResponse)->toResponse($jsonRequest)->getData(true))->toBe(['two_factor' => false])
        ->and((new VerifyEmailResponse)->toResponse($jsonRequest)->getStatusCode())->toBe(204);

    $user = User::factory()->create();
    $redirectRequest = Request::create('/two-factor-challenge', 'POST');
    $redirectRequest->setLaravelSession($this->app['session.store']);
    $redirectRequest->setUserResolver(fn (): User => $user);

    $response = (new TwoFactorLoginResponse)->toResponse($redirectRequest);

    expect($response->getTargetUrl())->toBe(route('dashboard', [
        'current_team' => $user->personalTeam()->slug,
    ]));
});

test('team invitations expose pending status and notification array payloads', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Coverage Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $acceptedInvitation = TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
        'role' => TeamRole::Admin,
    ]);

    expect($acceptedInvitation->isPending())->toBeFalse();

    $payload = (new TeamInvitationNotification($acceptedInvitation))->toArray((object) []);

    expect($payload)->toBe([
        'invitation_id' => $acceptedInvitation->id,
        'team_id' => $team->id,
        'team_name' => 'Coverage Team',
        'role' => TeamRole::Admin->value,
    ]);
});

test('team policy exposes baseline permissions directly', function () {
    $policy = new TeamPolicy;
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($policy->viewAny($member))->toBeTrue()
        ->and($policy->create($member))->toBeTrue()
        ->and($policy->view($member, $team))->toBeTrue()
        ->and($policy->view(User::factory()->create(), $team))->toBeFalse()
        ->and($policy->addMember($owner, $team))->toBeTrue()
        ->and($policy->addMember($member, $team))->toBeFalse()
        ->and(TeamRole::Owner->hasPermission(TeamPermission::AddMember))->toBeTrue();
});

test('production password defaults require enterprise strength', function () {
    $originalEnvironment = app()->environment();

    try {
        $this->app->detectEnvironment(fn (): string => 'production');

        (new AppServiceProvider($this->app))->boot();

        expect(PasswordRule::default()->toPasswordRulesString())
            ->toBe('minlength: 12; required: lower; required: upper; required: digit; required: special;');
    } finally {
        $this->app->detectEnvironment(fn (): string => $originalEnvironment);

        (new AppServiceProvider($this->app))->boot();
    }
});

test('fortify rate limiters expose two factor and passkey keys', function () {
    $twoFactorRequest = Request::create('/two-factor-challenge', 'POST');
    $twoFactorRequest->setLaravelSession($this->app['session.store']);
    $twoFactorRequest->session()->put('login.id', 123);

    $twoFactorLimit = RateLimiter::limiter('two-factor')($twoFactorRequest);

    $passkeyRequest = Request::create('/passkeys/login', 'POST', [
        'credential' => ['id' => 'credential-123'],
    ], server: [
        'REMOTE_ADDR' => '10.0.0.1',
    ]);
    $passkeyRequest->setLaravelSession($this->app['session.store']);

    $passkeyLimit = RateLimiter::limiter('passkeys')($passkeyRequest);

    expect($twoFactorLimit->key)->toBe(123)
        ->and($twoFactorLimit->maxAttempts)->toBe(5)
        ->and($passkeyLimit->key)->toBe('credential-123|10.0.0.1')
        ->and($passkeyLimit->maxAttempts)->toBe(10);
});

test('login ignores invalid invitation query codes', function () {
    $this
        ->get(route('login', ['invitation' => 'missing-code']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('teamInvitation', null));
});

test('team validation rules reject reserved names and invalid invitations', function () {
    $teamNameValidator = Validator::make(
        ['name' => 'settings'],
        ['name' => [new TeamName]],
    );

    $failed = false;

    (new ValidTeamInvitation(null))->validate(
        'invitation',
        'not-an-invitation',
        function () use (&$failed): void {
            $failed = true;
        },
    );

    expect($teamNameValidator->fails())->toBeTrue()
        ->and($failed)->toBeTrue();
});
