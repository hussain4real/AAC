<?php

use App\Http\Middleware\AuthenticateSdkClient;
use App\Models\Application;
use App\Models\Credential;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    // The tests that exercise the real Passport guard (no actingAsClient) load
    // the OAuth signing keys. CI does not generate them, so create on demand.
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }
});

test('a valid client credentials token resolves the application context', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->withOauthClient()->create();

    Passport::actingAsClient($credential->oauthClient, [], 'api');

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('application.id', $application->slug);

    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

test('a token whose client has no credential is rejected', function () {
    $orphanClient = app(ClientRepository::class)->createClientCredentialsGrantClient('Orphan');

    Passport::actingAsClient($orphanClient, [], 'api');

    $this->getJson('/api/v1/manifest')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'unknown_client');
});

test('a credential for a deleted application is rejected', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->withOauthClient()->create();
    $application->delete();

    Passport::actingAsClient($credential->oauthClient, [], 'api');

    $this->getJson('/api/v1/manifest')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'unknown_client');
});

test('a revoked credential cannot authenticate', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->revoked()->withOauthClient()->create();

    Passport::actingAsClient($credential->oauthClient, [], 'api');

    $this->getJson('/api/v1/manifest')
        ->assertForbidden()
        ->assertJsonPath('error', 'credential_revoked');
});

test('an unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/manifest')->assertUnauthorized();
});

test('the middleware returns invalid_token when no client resolves', function () {
    $middleware = new AuthenticateSdkClient;

    $response = $middleware->handle(Request::create('/api/v1/manifest'), fn () => response('ok'));

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode((string) $response->getContent(), true)['error'])->toBe('invalid_token');
});

test('resolving SDK context without the middleware throws', function () {
    SdkContext::fromRequest(Request::create('/api/v1/manifest'));
})->throws(RuntimeException::class);
