<?php

use Maacc\Sdk\Auth\AccessToken;
use Maacc\Sdk\Auth\TokenProvider;
use Maacc\Sdk\Exceptions\MaaccApiException;
use Maacc\Sdk\Exceptions\MaaccException;
use Maacc\Sdk\Exceptions\TransportException;
use Maacc\Sdk\Http\HttpResponse;
use Maacc\Sdk\MaaccConfig;
use Tests\Support\Sdk\FakeTransport;

/**
 * Unit coverage for configuration, the OAuth token lifecycle, and the HTTP
 * response value object.
 */
it('builds configuration from an environment map', function () {
    $config = MaaccConfig::fromEnvironment([
        'MAACC_BASE_URL' => 'https://maacc.test',
        'MAACC_CLIENT_ID' => 'cid',
        'MAACC_CLIENT_SECRET' => 'secret',
        'MAACC_TIMEOUT' => '45',
    ]);

    expect($config->baseUrl)->toBe('https://maacc.test')
        ->and($config->clientId)->toBe('cid')
        ->and($config->timeout)->toBe(45);
});

it('reads configuration from real process environment variables', function () {
    putenv('MAACC_BASE_URL=https://maacc.test');
    putenv('MAACC_CLIENT_ID=cid');
    putenv('MAACC_CLIENT_SECRET=secret');

    try {
        expect(MaaccConfig::fromEnvironment()->clientId)->toBe('cid');
    } finally {
        putenv('MAACC_BASE_URL');
        putenv('MAACC_CLIENT_ID');
        putenv('MAACC_CLIENT_SECRET');
    }
});

it('lists every missing required environment variable', function () {
    expect(fn () => MaaccConfig::fromEnvironment(['MAACC_BASE_URL' => 'https://maacc.test']))
        ->toThrow(MaaccException::class, 'MAACC_CLIENT_ID, MAACC_CLIENT_SECRET');
});

it('normalises slashes when building urls', function () {
    expect((new MaaccConfig('https://maacc.test/', 'c', 's'))->url('/api/v1/manifest'))
        ->toBe('https://maacc.test/api/v1/manifest')
        ->and((new MaaccConfig('', 'c', 's'))->url('/oauth/token'))
        ->toBe('/oauth/token');
});

it('caches the token until it nears expiry, then refreshes', function () {
    $now = 1_000;
    $transport = (new FakeTransport)
        ->push(200, ['access_token' => 'a', 'expires_in' => 100])
        ->push(200, ['access_token' => 'b', 'expires_in' => 100]);

    $provider = new TokenProvider(new MaaccConfig('https://maacc.test', 'c', 's'), $transport, function () use (&$now): int {
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

    $provider = new TokenProvider(new MaaccConfig('https://maacc.test', 'c', 's'), $transport);

    expect($provider->token())->toBe('a')
        ->and($provider->refresh())->toBe('b');
});

it('raises a typed exception when the token exchange is rejected', function () {
    $transport = (new FakeTransport)->push(401, ['error' => 'invalid_client', 'message' => 'bad credentials']);
    $provider = new TokenProvider(new MaaccConfig('https://maacc.test', 'c', 's'), $transport);

    expect(fn () => $provider->token())->toThrow(MaaccApiException::class, 'bad credentials');
});

it('defaults the token lifetime when MAACC omits expires_in', function () {
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
    $exception = MaaccApiException::fromResponse(new HttpResponse(500, 'Internal Server Error'));

    expect($exception->errorCode)->toBe('http_error')
        ->and($exception->status)->toBe(500)
        ->and($exception->validationErrors())->toBe([]);
});
