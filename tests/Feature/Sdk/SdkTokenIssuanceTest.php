<?php

use App\Actions\Maac\CreateCredential;
use App\Actions\Maac\RevokeCredential;
use App\Actions\Maac\RotateCredential;
use App\Models\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Passport requires signing keys to issue/validate tokens. CI does not
    // generate them, so create them on demand for these end-to-end tests.
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    [$this->owner, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create();
});

/**
 * Exchange a credential's client id/secret for an SDK access token.
 */
function requestToken(string $clientId, string $secret): TestResponse
{
    return test()->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $secret,
    ]);
}

test('a credential can be exchanged for a short-lived SDK token that authenticates the API', function () {
    $result = app(CreateCredential::class)->handle($this->application, $this->owner, ['environment' => 'production']);

    $response = requestToken($result->credential->client_id, $result->plainSecret)->assertOk();

    expect($response->json('access_token'))->toBeString()
        ->and($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('expires_in'))->toBeLessThanOrEqual(3600);

    $this->withToken($response->json('access_token'))
        ->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('application.id', $this->application->slug);
});

test('rotating a credential invalidates the old secret and accepts the new one', function () {
    $result = app(CreateCredential::class)->handle($this->application, $this->owner, ['environment' => 'production']);
    $clientId = $result->credential->client_id;
    $oldSecret = $result->plainSecret;

    $rotated = app(RotateCredential::class)->handle($result->credential);

    requestToken($clientId, $oldSecret)->assertStatus(401);
    requestToken($clientId, $rotated->plainSecret)->assertOk();
});

test('a revoked credential can no longer issue tokens', function () {
    $result = app(CreateCredential::class)->handle($this->application, $this->owner, ['environment' => 'production']);

    app(RevokeCredential::class)->handle($result->credential);

    requestToken($result->credential->client_id, $result->plainSecret)->assertStatus(401);
});
