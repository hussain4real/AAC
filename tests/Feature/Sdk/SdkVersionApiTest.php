<?php

use App\Enums\Environment;
use App\Models\Application;
use App\Models\Credential;
use Laravel\Passport\Passport;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create([
        'environment' => Environment::Production,
    ]);
    $this->credential = Credential::factory()->for($this->application)->withOauthClient()->create([
        'environment' => Environment::Production,
    ]);

    config()->set('maac.sdk.api_version', '0.0.1');
    config()->set('maac.sdk.minimum_client_version', '0.0.1');
    config()->set('maac.sdk.current_client_version', '0.2.0');

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
});

test('the sdk endpoint describes the versioned contract and supported packages', function () {
    $response = $this->getJson('/api/v1/sdk')->assertOk();

    $response->assertJsonPath('api_version', '0.0.1')
        ->assertJsonPath('minimum_client_version', '0.0.1')
        ->assertJsonPath('current_client_version', '0.2.0');

    expect($response->json('languages'))->not->toBeEmpty()
        ->and(collect($response->json('packages'))->pluck('language')->all())
        ->toContain('php', 'typescript')
        ->and(collect($response->json('packages'))->firstWhere('language', 'php'))
        ->toMatchArray([
            'name' => 'maac/sdk',
            'registry' => 'composer-vcs',
            'status' => 'supported',
        ])
        ->and($response->json('deprecations'))->toBe([]);
});

test('it reports a reported client version as compatible via the header', function () {
    $this->withHeaders(['X-Maac-Sdk-Version' => '0.1.0', 'X-Maac-Sdk-Language' => 'php'])
        ->getJson('/api/v1/sdk')
        ->assertOk()
        ->assertJsonPath('compatibility.status', 'compatible')
        ->assertJsonPath('compatibility.compatible', true)
        ->assertJsonPath('compatibility.client_version', '0.1.0')
        ->assertJsonPath('compatibility.language', 'php');
});

test('it flags an outdated client version as requiring an upgrade', function () {
    $this->withHeaders(['X-Maac-Sdk-Version' => '0.0.0'])
        ->getJson('/api/v1/sdk')
        ->assertOk()
        ->assertJsonPath('compatibility.status', 'upgrade_required')
        ->assertJsonPath('compatibility.compatible', false)
        ->assertJsonPath('compatibility.upgrade_required', true);
});

test('it accepts the client version from the query string as a fallback', function () {
    $this->getJson('/api/v1/sdk?client_version=2.0.0&language=typescript')
        ->assertOk()
        ->assertJsonPath('compatibility.status', 'ahead')
        ->assertJsonPath('compatibility.client_version', '2.0.0')
        ->assertJsonPath('compatibility.language', 'typescript');
});

test('an unreported client version is unknown', function () {
    $this->getJson('/api/v1/sdk')
        ->assertOk()
        ->assertJsonPath('compatibility.status', 'unknown')
        ->assertJsonPath('compatibility.client_version', null);
});

test('every v1 response carries the api version header', function () {
    $this->getJson('/api/v1/sdk')
        ->assertOk()
        ->assertHeader('X-Maac-Api-Version', '0.0.1');

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertHeader('X-Maac-Api-Version', '0.0.1');
});

test('the manifest embeds the versioned sdk contract block', function () {
    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('api_version', '0.0.1')
        ->assertJsonPath('sdk.api_version', '0.0.1')
        ->assertJsonPath('sdk.minimum_client_version', '0.0.1')
        ->assertJsonPath('sdk.current_client_version', '0.2.0');
});
