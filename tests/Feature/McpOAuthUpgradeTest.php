<?php

use Illuminate\Http\Request;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

it('rejects OAuth userinfo and localhost hostname suffix bypasses', function (string $uri) {
    config(['mcp.redirect_domains' => ['http://localhost', 'http://127.0.0.1']]);
    $response = (new OAuthRegisterController)(Request::create('/oauth/register', 'POST', ['redirect_uris' => [$uri]]));
    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'])->toBe('invalid_redirect_uri');
})->with([
    'http://localhost@attacker.example/callback',
    'http://127.0.0.1@attacker.example/callback',
    'http://localhost.attacker.example/callback',
    'http://127.0.0.1.attacker.example/callback',
    'http://localhost:password@attacker.example/callback',
]);

it('keeps legitimate localhost OAuth redirects with ports', function (string $uri) {
    config(['mcp.redirect_domains' => ['http://localhost', 'http://127.0.0.1']]);
    app()->instance('Laravel\\Passport\\ClientRepository', new class
    {
        public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential, bool $enableDeviceFlow): object
        {
            return (object) ['id' => 'test-client', 'grant_types' => ['authorization_code'], 'redirect_uris' => $redirectUris];
        }
    });
    $response = (new OAuthRegisterController)(Request::create('/oauth/register', 'POST', ['redirect_uris' => [$uri]]));
    // Apps without Passport stop after URI validation; installed Passport uses the in-memory repository above.
    expect($response->getStatusCode())->toBe(class_exists('Laravel\\Passport\\ClientRepository') ? 201 : 500)
        ->and($response->getData(true)['error'] ?? null)->not->toBe('invalid_redirect_uri');
})->with(['http://localhost:8123/callback', 'http://127.0.0.1:8123/callback']);
