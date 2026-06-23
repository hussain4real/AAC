<?php

use App\Enums\HttpMethod;
use App\Enums\RemoteAuthType;
use App\Models\ToolContract;
use App\Support\Runtime\Remote\RemoteHttpToolExecutor;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['tools.example.com', '*.trusted.io']]);
    Http::preventStrayRequests();
});

function httpExecutor(): RemoteHttpToolExecutor
{
    return app(RemoteHttpToolExecutor::class);
}

function httpTool(array $config = [], array $output = ['result' => 'string']): ToolContract
{
    return ToolContract::factory()->remoteHttp($config)->create(['output_schema' => $output]);
}

it('executes a remote HTTP POST tool and returns the JSON object', function () {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'Doha', 'total' => 1])]);

    $result = httpExecutor()->execute(httpTool(), ['query' => 'port']);

    expect($result)->toBe(['result' => 'Doha', 'total' => 1]);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['query'] === 'port');
});

it('executes a GET tool sending arguments as query parameters', function () {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    httpExecutor()->execute(httpTool(['method' => HttpMethod::Get->value]), ['q' => 'x']);

    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), 'q=x'));
});

it('supports each configured HTTP method', function (string $method) {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    httpExecutor()->execute(httpTool(['method' => $method]), ['q' => 'x']);

    Http::assertSent(fn ($request) => $request->method() === strtoupper($method));
})->with(['get', 'put', 'patch', 'delete']);

it('skips empty allowlist entries when matching the host', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['', 'tools.example.com']]);
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    expect(httpExecutor()->execute(httpTool(), []))->toBe(['result' => 'ok']);
});

it('matches a wildcard allowlist entry', function () {
    Http::fake(['api.trusted.io/*' => Http::response(['result' => 'ok'])]);

    $result = httpExecutor()->execute(httpTool(['endpoint' => 'https://api.trusted.io/run']), []);

    expect($result)->toBe(['result' => 'ok']);
});

it('blocks an endpoint host that is not on the allowlist', function () {
    expect(fn () => httpExecutor()->execute(httpTool(['endpoint' => 'https://evil.example.org/x']), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_blocked'));
});

it('blocks a denied loopback host even if allowlisted', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['localhost']]);

    expect(fn () => httpExecutor()->execute(httpTool(['endpoint' => 'http://localhost/x']), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_blocked'));
});

it('blocks an endpoint that is not a valid HTTP URL', function () {
    expect(fn () => httpExecutor()->execute(httpTool(['endpoint' => 'ftp://tools.example.com/x']), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_blocked'));
});

it('maps a 401 to a controlled unauthorized failure', function () {
    Http::fake(['tools.example.com/*' => Http::response('', 401)]);

    expect(fn () => httpExecutor()->execute(httpTool(), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_unauthorized'));
});

it('maps a non-success status to a controlled failure', function () {
    Http::fake(['tools.example.com/*' => Http::response('nope', 404)]);

    expect(fn () => httpExecutor()->execute(httpTool(), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_failed'));
});

it('retries server errors then succeeds', function () {
    Http::fake(['tools.example.com/*' => Http::sequence()
        ->push('', 500)
        ->push(['result' => 'recovered'])]);

    $result = httpExecutor()->execute(httpTool(['retry' => ['max_attempts' => 2, 'backoff_ms' => 0]]), []);

    expect($result)->toBe(['result' => 'recovered']);
    Http::assertSentCount(2);
});

it('fails after exhausting retries on server errors', function () {
    Http::fake(['tools.example.com/*' => Http::response('', 503)]);

    expect(fn () => httpExecutor()->execute(httpTool(['retry' => ['max_attempts' => 2, 'backoff_ms' => 1]]), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_failed'));
});

it('retries then maps an unreachable endpoint to a controlled failure', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    expect(fn () => httpExecutor()->execute(httpTool(['retry' => ['max_attempts' => 2, 'backoff_ms' => 1]]), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_unreachable'));
});

it('treats a non-JSON response body as invalid output', function () {
    Http::fake(['tools.example.com/*' => Http::response('not json', 200, ['Content-Type' => 'text/plain'])]);

    expect(fn () => httpExecutor()->execute(httpTool(), []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('remote_http_invalid_output'));
});

it('sends a bearer token when configured', function () {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    httpExecutor()->execute(httpTool(['auth' => ['type' => RemoteAuthType::Bearer->value, 'credential' => 'tok-1']]), []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok-1'));
});

it('sends a custom auth header when configured', function () {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    httpExecutor()->execute(httpTool(['auth' => ['type' => RemoteAuthType::Header->value, 'header' => 'X-Api-Key', 'credential' => 'k-9']]), []);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'k-9'));
});

it('defaults the header name to Authorization when none is given', function () {
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    httpExecutor()->execute(httpTool(['auth' => ['type' => RemoteAuthType::Header->value, 'credential' => 'k-9']]), []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'k-9'));
});

it('caps per-tool retries at the platform maximum', function () {
    config(['maac.runtime.remote_http.max_attempts' => 1]);
    Http::fake(['tools.example.com/*' => Http::response('', 503)]);

    expect(fn () => httpExecutor()->execute(httpTool(['retry' => ['max_attempts' => 9]]), []))
        ->toThrow(ToolExecutionException::class);

    Http::assertSentCount(1);
});
