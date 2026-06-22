<?php

use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Exceptions\MissingToolHandlerException;
use Maac\Sdk\Exceptions\RunNotResolvedException;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Sdk\FakeTransport;

/**
 * Unit coverage for the SDK client against a scripted transport — no MAAC, no
 * database — pinning the request shapes it sends and how it parses/raises on
 * MAAC's responses.
 */
function sdkClient(FakeTransport $transport): MaacClient
{
    return new MaacClient(new MaacConfig('https://maac.test', 'cid', 'secret'), $transport);
}

function tokenResponse(): array
{
    return ['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => 'tok-123'];
}

it('exchanges the credential for a token via the form grant', function () {
    $transport = (new FakeTransport)->push(200, tokenResponse());

    expect(sdkClient($transport)->authenticate())->toBe('tok-123');

    $request = $transport->request(0);
    expect($request->method)->toBe('POST')
        ->and($request->url)->toBe('https://maac.test/oauth/token')
        ->and($request->headers['Content-Type'])->toBe('application/x-www-form-urlencoded')
        ->and($request->body)->toContain('grant_type=client_credentials')
        ->and($request->body)->toContain('client_id=cid');
});

it('fetches and parses the manifest with a bearer token', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(200, [
            'application' => ['id' => 'cargo', 'name' => 'Cargo', 'environment' => 'production'],
            'agents' => [['slug' => 'ops', 'name' => 'Ops', 'version' => 'v1', 'status' => 'published', 'tools' => ['fetch']]],
            'tools' => [[
                'name' => 'fetch', 'version' => '1.0.0', 'schema_fingerprint' => 'fp-1',
                'input_schema' => ['query' => 'string'], 'output_schema' => ['records' => 'array'],
                'implementation' => ['status' => 'required'],
            ]],
        ]);

    $manifest = sdkClient($transport)->manifest();

    expect($manifest->environment)->toBe('production')
        ->and($manifest->agent('ops')?->tools)->toBe(['fetch'])
        ->and($manifest->tool('fetch')?->schemaFingerprint)->toBe('fp-1')
        ->and($manifest->tool('fetch')?->implementationStatus())->toBe('required')
        ->and($manifest->tool('fetch')?->isImplemented())->toBeFalse()
        ->and($transport->request(1)->headers['Authorization'])->toBe('Bearer tok-123');
});

it('negotiates compatibility with the MAAC sdk endpoint', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(200, [
            'api_version' => '1.0.0',
            'minimum_client_version' => '1.0.0',
            'current_client_version' => '1.4.0',
            'deprecations' => [['id' => 'legacy', 'removed_in' => '2.0.0']],
            'compatibility' => [
                'status' => 'compatible', 'compatible' => true, 'client_version' => '1.0.0',
                'api_version' => '1.0.0', 'minimum_client_version' => '1.0.0',
                'current_client_version' => '1.4.0', 'upgrade_required' => false,
            ],
        ]);

    $compatibility = sdkClient($transport)->compatibility();

    expect($compatibility->isCompatible())->toBeTrue()
        ->and($compatibility->status)->toBe('compatible')
        ->and($compatibility->apiVersion)->toBe('1.0.0')
        ->and($compatibility->currentClientVersion)->toBe('1.4.0')
        ->and($compatibility->deprecations)->toHaveCount(1)
        ->and($compatibility->requiresUpgrade())->toBeFalse();

    $request = $transport->request(1);
    expect($request->url)->toBe('https://maac.test/api/v1/sdk')
        ->and($request->headers['X-Maac-Sdk-Version'])->toBe(MaacClient::VERSION)
        ->and($request->headers['X-Maac-Sdk-Language'])->toBe('php');
});

it('reports registered handlers against the manifest', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(200, [
            'application' => ['environment' => 'production'],
            'agents' => [],
            'tools' => [[
                'name' => 'fetch', 'version' => '2.1.0', 'schema_fingerprint' => 'fp-9',
                'input_schema' => [], 'output_schema' => [], 'implementation' => ['status' => 'required'],
            ]],
        ])
        ->push(200, ['results' => [['tool' => 'fetch', 'accepted' => true, 'status' => 'implemented']]]);

    $registry = (new ToolHandlerRegistry)->registerCallable('fetch', fn (): array => []);
    $client = sdkClient($transport);

    $results = $client->reportHandlers($client->manifest(), $registry, 'php');

    expect($results[0]['status'])->toBe('implemented');

    $reportBody = json_decode((string) $transport->request(2)->body, true);
    expect($reportBody['implementations'][0])->toMatchArray([
        'tool' => 'fetch',
        'handler_name' => 'CallableToolHandler',
        'version' => '2.1.0',
        'schema_fingerprint' => 'fp-9',
        'language' => 'php',
    ]);
});

it('does not call the report endpoint when nothing matches the manifest', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(200, ['application' => ['environment' => 'production'], 'agents' => [], 'tools' => []]);

    $client = sdkClient($transport);
    $registry = (new ToolHandlerRegistry)->registerCallable('absent', fn (): array => []);

    expect($client->reportHandlers($client->manifest(), $registry))->toBe([]);
    expect($transport->requests)->toHaveCount(2); // token + manifest only
});

it('drives a paused run to completion through the registry', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(201, [
            'run_id' => 'run-1', 'agent_slug' => 'ops', 'status' => 'waiting_for_client',
            'usage' => ['tokens_in' => 5, 'tokens_out' => 0], 'cost' => 0.01,
            'tool_call' => ['id' => 'call-1', 'tool' => 'fetch', 'arguments' => ['query' => 'today'], 'output_schema' => ['records' => 'array']],
        ])
        ->push(200, [
            'run_id' => 'run-1', 'agent_slug' => 'ops', 'status' => 'completed',
            'usage' => ['tokens_in' => 5, 'tokens_out' => 7], 'cost' => 0.03, 'response' => 'All clear.',
        ]);

    $captured = [];
    $registry = (new ToolHandlerRegistry)->registerCallable('fetch', function (array $arguments) use (&$captured): array {
        $captured = $arguments;

        return ['records' => ['a'], 'total' => 1];
    });

    $run = sdkClient($transport)->run('ops', 'Summarize', $registry, 'unit');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All clear.')
        ->and($run->tokensOut)->toBe(7)
        ->and($captured)->toBe(['query' => 'today']);

    $submit = $transport->request(2);
    expect($submit->url)->toBe('https://maac.test/api/v1/runs/run-1/tool-results');
    $body = json_decode((string) $submit->body, true);
    expect($body['tool_call_id'])->toBe('call-1')
        ->and($body['result'])->toBe(['records' => ['a'], 'total' => 1]);
});

it('throws when MAAC pauses for an unregistered tool', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(201, [
            'run_id' => 'run-1', 'agent_slug' => 'ops', 'status' => 'waiting_for_client',
            'usage' => ['tokens_in' => 1, 'tokens_out' => 0], 'cost' => 0,
            'tool_call' => ['id' => 'call-1', 'tool' => 'fetch', 'arguments' => [], 'output_schema' => null],
        ]);

    expect(fn () => sdkClient($transport)->run('ops', 'x', new ToolHandlerRegistry))
        ->toThrow(MissingToolHandlerException::class, 'fetch');
});

it('returns a non-completed terminal run without throwing', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(201, [
            'run_id' => 'run-1', 'agent_slug' => 'ops', 'status' => 'failed',
            'usage' => ['tokens_in' => 1, 'tokens_out' => 0], 'cost' => 0, 'error' => 'model not approved',
        ]);

    $run = sdkClient($transport)->run('ops', 'x', new ToolHandlerRegistry);

    expect($run->isCompleted())->toBeFalse()
        ->and($run->isTerminal())->toBeTrue()
        ->and($run->status)->toBe('failed')
        ->and($run->error)->toBe('model not approved');
});

it('gives up after the iteration budget when a run keeps pausing', function () {
    $waiting = [
        'run_id' => 'run-1', 'agent_slug' => 'ops', 'status' => 'waiting_for_client',
        'usage' => ['tokens_in' => 1, 'tokens_out' => 0], 'cost' => 0,
        'tool_call' => ['id' => 'call-1', 'tool' => 'fetch', 'arguments' => [], 'output_schema' => null],
    ];
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(201, $waiting)
        ->push(200, $waiting)
        ->push(200, $waiting);

    $registry = (new ToolHandlerRegistry)->registerCallable('fetch', fn (): array => []);

    expect(fn () => sdkClient($transport)->run('ops', 'x', $registry, null, 2))
        ->toThrow(RunNotResolvedException::class);
});

it('raises a typed exception carrying the MAAC error code and status', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(403, ['error' => 'credential_revoked', 'message' => 'This credential has been revoked.']);

    try {
        sdkClient($transport)->manifest();
        $this->fail('Expected a MaacApiException.');
    } catch (MaacApiException $exception) {
        expect($exception->errorCode)->toBe('credential_revoked')
            ->and($exception->status)->toBe(403)
            ->and($exception->getMessage())->toBe('This credential has been revoked.');
    }
});

it('surfaces schema validation errors from an invalid tool result', function () {
    $transport = (new FakeTransport)
        ->push(200, tokenResponse())
        ->push(422, ['error' => 'invalid_tool_result', 'message' => 'Bad result.', 'errors' => ['records is required', 'total is required']]);

    try {
        sdkClient($transport)->submitToolResult('run-1', 'call-1', ['nope' => true]);
        $this->fail('Expected a MaacApiException.');
    } catch (MaacApiException $exception) {
        expect($exception->validationErrors())->toBe(['records is required', 'total is required']);
    }
});

it('refreshes the token and retries once on a 401', function () {
    $transport = (new FakeTransport)
        ->push(200, ['access_token' => 'tok-1', 'expires_in' => 3600])
        ->push(401, ['error' => 'invalid_token', 'message' => 'expired'])
        ->push(200, ['access_token' => 'tok-2', 'expires_in' => 3600])
        ->push(200, ['application' => ['environment' => 'production'], 'agents' => [], 'tools' => []]);

    $manifest = sdkClient($transport)->manifest();

    expect($manifest->environment)->toBe('production')
        ->and($transport->request(1)->headers['Authorization'])->toBe('Bearer tok-1')
        ->and($transport->request(3)->headers['Authorization'])->toBe('Bearer tok-2');
});
