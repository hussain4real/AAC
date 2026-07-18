<?php

use App\Exceptions\OutboundRequestBlocked;
use App\Support\Outbound\DnsResolver;
use App\Support\Outbound\OutboundHttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\Outbound\FakeDnsResolver;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('revalidates every redirect and blocks a redirect to a private destination', function () {
    $dns = app(DnsResolver::class);
    expect($dns)->toBeInstanceOf(FakeDnsResolver::class);
    $dns->set('private.example.com', ['10.0.0.10']);

    Http::fake([
        'origin.example.com/*' => Http::response('', 302, ['Location' => 'https://private.example.com/admin']),
    ]);

    expect(fn () => app(OutboundHttpClient::class)->send('webhook', 'POST', 'https://origin.example.com/hook', [
        'body' => '{}',
    ]))->toThrow(OutboundRequestBlocked::class, 'resolves to a non-public address');

    Http::assertSentCount(1);
});

it('follows an approved relative redirect and converts a 303 request to GET', function () {
    Http::fake([
        'api.example.com/start' => Http::response('', 303, ['Location' => '/result']),
        'api.example.com/result' => Http::response(['ok' => true]),
    ]);

    $response = app(OutboundHttpClient::class)->send('remote_http', 'POST', 'https://api.example.com/start', [
        'json' => ['query' => 'Doha'],
        'max_redirects' => 2,
    ]);

    expect($response->json())->toBe(['ok' => true]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.com/result'
        && $request->method() === 'GET'
        && $request->data() === []);
});

it('strips credentials and configured sensitive headers on a cross-host redirect', function () {
    config(['maacc.outbound.sensitive_headers' => ['X-Tenant-Secret']]);
    Http::fake([
        'origin.example.com/*' => Http::response('', 307, ['Location' => 'https://other.example.com/result']),
        'other.example.com/*' => Http::response(['ok' => true]),
    ]);

    app(OutboundHttpClient::class)->send('sso', 'GET', 'https://origin.example.com/start', [
        'headers' => [
            'Authorization' => 'Bearer secret',
            'Cookie' => 'session=secret',
            'X-Api-Key' => 'key',
            'X-Tenant-Secret' => 'tenant-secret',
            'X-Trace-Id' => 'trace-1',
        ],
    ]);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://other.example.com/result') {
            return false;
        }

        return ! $request->hasHeader('Authorization')
            && ! $request->hasHeader('Cookie')
            && ! $request->hasHeader('X-Api-Key')
            && ! $request->hasHeader('X-Tenant-Secret')
            && $request->hasHeader('X-Trace-Id', 'trace-1');
    });
});

it('blocks DNS rebinding before the redirected request is sent', function () {
    $resolver = new class implements DnsResolver
    {
        private int $calls = 0;

        public function resolve(string $host): array
        {
            $this->calls++;

            return $this->calls < 3 ? ['1.1.1.1'] : ['127.0.0.1'];
        }
    };
    app()->instance(DnsResolver::class, $resolver);

    Http::fake([
        'rebind.example.com/start' => Http::response('', 307, ['Location' => '/next']),
        'rebind.example.com/next' => Http::response(['unsafe' => true]),
    ]);

    expect(fn () => app(OutboundHttpClient::class)->send('mcp', 'POST', 'https://rebind.example.com/start', [
        'body' => '{}',
    ]))->toThrow(OutboundRequestBlocked::class, 'resolves to a non-public address');

    Http::assertSentCount(1);
});

it('fails closed when the redirect limit is exceeded', function () {
    Http::fake(fn (Request $request) => Http::response('', 302, ['Location' => $request->url()]));

    expect(fn () => app(OutboundHttpClient::class)->send('webhook', 'GET', 'https://loop.example.com/start', [
        'max_redirects' => 1,
    ]))->toThrow(OutboundRequestBlocked::class, 'redirect limit');

    Http::assertSentCount(2);
});

it('keeps direct framework HTTP calls behind the unified outbound client', function () {
    $violations = collect(File::allFiles(app_path()))
        ->reject(fn (SplFileInfo $file): bool => $file->getRealPath() === app_path('Support/Outbound/OutboundHttpClient.php'))
        ->filter(function (SplFileInfo $file): bool {
            $contents = $file->getContents();

            return str_contains($contents, 'Http::') || str_contains($contents, 'Client::web(');
        })
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
