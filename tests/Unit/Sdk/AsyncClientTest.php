<?php

use Maacc\Sdk\Exceptions\MaaccApiException;
use Maacc\Sdk\Exceptions\RunNotResolvedException;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Resources\Run;
use Maacc\Sdk\Resources\WebhookEndpoint;
use Maacc\Sdk\Tools\ToolHandlerRegistry;
use Maacc\Sdk\Webhooks\WebhookSignature;
use Tests\Support\Sdk\FakeTransport;

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function runStatus(string $status, array $extra = []): array
{
    return [
        'run_id' => 'run-1',
        'agent_slug' => 'ops',
        'status' => $status,
        'usage' => ['tokens_in' => 1, 'tokens_out' => 1],
        'cost' => 0.01,
        ...$extra,
    ];
}

/**
 * @return array<string, mixed>
 */
function asyncToken(): array
{
    return ['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => 'tok'];
}

function asyncSdkClient(FakeTransport $transport): MaaccClient
{
    return new MaaccClient(new MaaccConfig('https://maacc.test', 'cid', 'secret'), $transport);
}

it('sends the requested mode when starting a run', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(202, runStatus('queued'));

    $run = asyncSdkClient($transport)->startRun('ops', 'go', mode: MaaccClient::MODE_ASYNC);

    expect($run->status)->toBe('queued');
    $body = json_decode((string) $transport->request(1)->body, true);
    expect($body)->toBe(['input' => 'go', 'mode' => 'async']);
});

it('polls an async run until it settles', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(200, runStatus('queued'))
        ->push(200, runStatus('running'))
        ->push(200, runStatus('completed', ['response' => 'Done.']));

    $run = asyncSdkClient($transport)->pollRun('run-1', maxAttempts: 10, intervalMs: 0);

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('Done.');
});

it('throws when an async run does not settle within the attempt budget', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(200, runStatus('running'))
        ->push(200, runStatus('running'))
        ->push(200, runStatus('running'));

    expect(fn () => asyncSdkClient($transport)->pollRun('run-1', maxAttempts: 2, intervalMs: 0))
        ->toThrow(RunNotResolvedException::class);
});

it('drives an async run through a client tool with polling', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(202, runStatus('queued'))
        ->push(200, runStatus('waiting_for_client', [
            'tool_call' => ['id' => 'call-1', 'tool' => 'fetch', 'arguments' => ['query' => 'today'], 'output_schema' => ['records' => 'array']],
        ]))
        ->push(202, runStatus('running'))
        ->push(200, runStatus('completed', ['response' => 'All clear.']));

    $registry = (new ToolHandlerRegistry)->registerCallable('fetch', fn (): array => ['records' => ['a'], 'total' => 1]);

    $run = asyncSdkClient($transport)->runAsync('ops', 'Summarize', $registry, 'unit', ['intervalMs' => 0]);

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All clear.');
});

it('registers, lists, and deletes a webhook endpoint', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(201, ['id' => 'wh-1', 'url' => 'https://app.test/hooks', 'events' => ['run.completed'], 'environment' => 'production', 'status' => 'pending_verification', 'secret' => 'whsec_abc'])
        ->push(200, ['verified' => true, 'id' => 'wh-1', 'url' => 'https://app.test/hooks', 'events' => ['run.completed'], 'environment' => 'production', 'status' => 'active'])
        ->push(200, ['data' => [['id' => 'wh-1', 'url' => 'https://app.test/hooks', 'events' => ['*'], 'environment' => 'production', 'status' => 'active']]])
        ->push(204, []);

    $client = asyncSdkClient($transport);

    $endpoint = $client->registerWebhook('https://app.test/hooks', ['run.completed']);
    expect($endpoint)->toBeInstanceOf(WebhookEndpoint::class)
        ->and($endpoint->secret)->toBe('whsec_abc');
    expect(json_decode((string) $transport->request(1)->body, true))->toBe(['url' => 'https://app.test/hooks', 'events' => ['run.completed']]);

    $verified = $client->verifyWebhook('wh-1');
    expect($verified->status)->toBe('active')
        ->and($transport->request(2)->url)->toEndWith('/api/v1/webhook-endpoints/wh-1/verify');

    $list = $client->listWebhooks();
    expect($list)->toHaveCount(1)
        ->and($list[0]->secret)->toBeNull();

    $client->deleteWebhook('wh-1');
    expect($transport->request(4)->method)->toBe('DELETE');
});

it('surfaces a controlled error when deleting an unknown endpoint', function () {
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(404, ['error' => 'webhook_endpoint_not_found', 'message' => 'missing']);

    expect(fn () => asyncSdkClient($transport)->deleteWebhook('missing'))
        ->toThrow(MaaccApiException::class);
});

it('issues and sends a signed caller context and parses it from the run', function () {
    $claims = ['v' => 1, 'sub' => 'user:42', 'roles' => ['viewer'], 'corr' => 'corr_sdk'];
    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->push(201, ['envelope' => 'payload.signature', 'claims' => $claims])
        ->push(201, [...runStatus('completed'), 'caller_context' => $claims]);
    $client = asyncSdkClient($transport);

    $context = $client->issueCallerContext('user:42', roles: ['viewer'], correlationId: 'corr_sdk');
    $run = $client->startRun('ops', 'Status?', 'legacy-label', callerContext: $context);

    expect($context->envelope)->toBe('payload.signature')
        ->and($context->claims)->toBe($claims)
        ->and($run->callerContext)->toBe($claims)
        ->and($transport->request(2)->headers['Idempotency-Key'])->toStartWith('sdk_')
        ->and(json_decode((string) $transport->request(2)->body, true))->toMatchArray([
            'input' => 'Status?',
            'caller' => 'legacy-label',
            'caller_context' => 'payload.signature',
        ]);
});

it('parses a run stream into events, skipping the sentinel', function () {
    $sse = "event: run.event\ndata: {\"type\":\"run_requested\",\"sequence\":0}\n\n"
        ."event: run.state\ndata: {\"status\":\"completed\"}\n\n"
        ."event: update\ndata: </stream>\n\n";

    $transport = (new FakeTransport)
        ->push(200, asyncToken())
        ->pushRaw(200, $sse);

    $events = asyncSdkClient($transport)->streamRun('run-1');

    expect($events)->toHaveCount(2)
        ->and($events[0]->event)->toBe('run.event')
        ->and($events[0]->data['type'])->toBe('run_requested')
        ->and($events[1]->event)->toBe('run.state')
        ->and($events[1]->data['status'])->toBe('completed');
});

it('exposes the settled state of a run', function () {
    expect(Run::fromArray(runStatus('completed'))->isSettled())->toBeTrue()
        ->and(Run::fromArray(runStatus('waiting_for_client'))->isSettled())->toBeTrue()
        ->and(Run::fromArray(runStatus('running'))->isSettled())->toBeFalse();
});

it('verifies a webhook signature only within tolerance', function () {
    $signature = 'sha256='.WebhookSignature::sign('{"a":1}', '1000', 'sec');

    expect(WebhookSignature::verify('{"a":1}', $signature, '1000', 'sec', 300, 1100))->toBeTrue()
        ->and(WebhookSignature::verify('{"a":1}', $signature, '1000', 'sec', 300, 9000))->toBeFalse()
        ->and(WebhookSignature::verify('{"a":2}', $signature, '1000', 'sec', 300, 1000))->toBeFalse()
        ->and(WebhookSignature::verify('{"a":1}', $signature, 'nan', 'sec', 300, 1000))->toBeFalse();
});
