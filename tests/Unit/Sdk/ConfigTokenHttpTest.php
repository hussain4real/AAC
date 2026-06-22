<?php

use Maac\Sdk\Auth\AccessToken;
use Maac\Sdk\Auth\TokenProvider;
use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Exceptions\MaacException;
use Maac\Sdk\Exceptions\TransportException;
use Maac\Sdk\Http\HttpResponse;
use Maac\Sdk\MaacConfig;
use Tests\Support\Sdk\FakeTransport;

/**
 * Unit coverage for configuration, the OAuth token lifecycle, and the HTTP
 * response value object.
 */
it('builds configuration from an environment map', function () {
    $config = MaacConfig::fromEnvironment([
        'MAAC_BASE_URL' => 'https://maac.test',
        'MAAC_CLIENT_ID' => 'cid',
        'MAAC_CLIENT_SECRET' => 'secret',
        'MAAC_TIMEOUT' => '45',
    ]);

    expect($config->baseUrl)->toBe('https://maac.test')
        ->and($config->clientId)->toBe('cid')
        ->and($config->timeout)->toBe(45);
});

it('reads configuration from real process environment variables', function () {
    putenv('MAAC_BASE_URL=https://maac.test');
    putenv('MAAC_CLIENT_ID=cid');
    putenv('MAAC_CLIENT_SECRET=secret');

    try {
        expect(MaacConfig::fromEnvironment()->clientId)->toBe('cid');
    } finally {
        putenv('MAAC_BASE_URL');
        putenv('MAAC_CLIENT_ID');
        putenv('MAAC_CLIENT_SECRET');
    }
});

it('lists every missing required environment variable', function () {
    expect(fn () => MaacConfig::fromEnvironment(['MAAC_BASE_URL' => 'https://maac.test']))
        ->toThrow(MaacException::class, 'MAAC_CLIENT_ID, MAAC_CLIENT_SECRET');
});

it('normalises slashes when building urls', function () {
    expect((new MaacConfig('https://maac.test/', 'c', 's'))->url('/api/v1/manifest'))
        ->toBe('https://maac.test/api/v1/manifest')
        ->and((new MaacConfig('', 'c', 's'))->url('/oauth/token'))
        ->toBe('/oauth/token');
});

it('caches the token until it nears expiry, then refreshes', function () {
    $now = 1_000;
    $transport = (new FakeTransport)
        ->push(200, ['access_token' => 'a', 'expires_in' => 100])
        ->push(200, ['access_token' => 'b', 'expires_in' => 100]);

    $provider = new TokenProvider(new MaacConfig('https://maac.test', 'c', 's'), $transport, function () use (&$now): int {
        return $now;
    });

    expect($provider->token())->toBe('a')
        ->and($provider->token())->toBe('a')
        ->and($transport->requests)->toHaveCount(1);

    $now = 1_200; // past expiry (1000 + 100 - 30 safety)

    expect($provider->token())->toBe('b')
        ->and($transport->requests)->toHaveCount(2);
});

it('forces a fresh exchange on refresh', function () {
    $transport = (new FakeTransport)
        ->push(200, ['access_token' => 'a', 'expires_in' => 3_600])
        ->push(200, ['access_token' => 'b', 'expires_in' => 3_600]);

    $provider = new TokenProvider(new MaacConfig('https://maac.test', 'c', 's'), $transport);

    expect($provider->token())->toBe('a')
        ->and($provider->refresh())->toBe('b');
});

it('raises a typed exception when the token exchange is rejected', function () {
    $transport = (new FakeTransport)->push(401, ['error' => 'invalid_client', 'message' => 'bad credentials']);
    $provider = new TokenProvider(new MaacConfig('https://maac.test', 'c', 's'), $transport);

    expect(fn () => $provider->token())->toThrow(MaacApiException::class, 'bad credentials');
});

it('defaults the token lifetime when MAAC omits expires_in', function () {
    $token = AccessToken::fromTokenResponse(['access_token' => 'x'], 0);

    expect($token->token)->toBe('x')
        ->and($token->isExpired(3_500))->toBeFalse()
        ->and($token->isExpired(3_600))->toBeTrue();
});

it('decodes, guards, and classifies http responses', function () {
    expect((new HttpResponse(200, '{"a":1}'))->json())->toBe(['a' => 1])
        ->and((new HttpResponse(204, ''))->json())->toBe([])
        ->and((new HttpResponse(200, ''))->successful())->toBeTrue()
        ->and((new HttpResponse(404, ''))->successful())->toBeFalse();

    expect(fn () => (new HttpResponse(500, '<html>'))->json())->toThrow(TransportException::class);
    expect(fn () => (new HttpResponse(200, '123'))->json())->toThrow(TransportException::class);
});

it('falls back to a generic code for a non-envelope error body', function () {
    $exception = MaacApiException::fromResponse(new HttpResponse(500, 'Internal Server Error'));

    expect($exception->errorCode)->toBe('http_error')
        ->and($exception->status)->toBe(500)
        ->and($exception->validationErrors())->toBe([]);
});
