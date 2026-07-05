<?php

use App\Actions\Maacc\CreateCredential;
use App\Enums\AgentStatus;
use App\Enums\CredentialStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaaccE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maacc\Sdk\Exceptions\MaaccApiException;
use Maacc\Sdk\Exceptions\MissingToolHandlerException;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B negative integration matrix: an external consumer hitting the failure
 * modes it must handle — revoked credentials, stale/incompatible implementation
 * reports, cross-tenant agent access, and a missing local handler — all surface
 * as typed, catchable SDK exceptions rather than hangs or generic errors.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaaccE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaaccE2ESeeder::APP_SLUG);
    $this->team = $this->application->team;

    $issued = app(CreateCredential::class)->handle(
        $this->application,
        User::firstWhere('email', MaaccE2ESeeder::USER_EMAIL),
        ['environment' => 'production'],
    );
    $this->credential = $issued->credential;
    $this->client = new MaaccClient(
        new MaaccConfig('', $issued->credential->client_id, $issued->plainSecret),
        new KernelTransport($this),
    );
});

it('rejects a revoked credential with a typed error', function () {
    $this->credential->forceFill([
        'status' => CredentialStatus::Revoked,
        'revoked_at' => now(),
    ])->save();

    try {
        $this->client->manifest();
        $this->fail('Expected a MaaccApiException for the revoked credential.');
    } catch (MaaccApiException $exception) {
        expect($exception->errorCode)->toBe('credential_revoked')
            ->and($exception->status)->toBe(403);
    }
});

it('marks a stale implementation version as outdated', function () {
    $tool = $this->client->manifest()->tool(MaaccE2ESeeder::TOOL_SLUG);

    $results = $this->client->reportImplementations([[
        'tool' => $tool->name,
        'handler_name' => 'StaleHandler',
        'version' => '0.0.1',
        'schema_fingerprint' => $tool->schemaFingerprint,
        'language' => 'php',
    ]]);

    expect($results[0]['accepted'])->toBeTrue()
        ->and($results[0]['status'])->toBe('outdated');
});

it('marks a mismatched schema fingerprint as incompatible', function () {
    $tool = $this->client->manifest()->tool(MaaccE2ESeeder::TOOL_SLUG);

    $results = $this->client->reportImplementations([[
        'tool' => $tool->name,
        'handler_name' => 'MismatchedHandler',
        'version' => $tool->version,
        'schema_fingerprint' => 'not-the-real-fingerprint',
        'language' => 'php',
    ]]);

    expect($results[0]['status'])->toBe('incompatible');
});

it('reports an unknown tool as not found without failing the batch', function () {
    $results = $this->client->reportImplementations([[
        'tool' => 'no-such-tool',
        'handler_name' => 'Ghost',
        'version' => '1.0.0',
        'language' => 'php',
    ]]);

    expect($results[0]['accepted'])->toBeFalse()
        ->and($results[0]['error'])->toBe('tool_not_found');
});

it('cannot invoke an agent owned by another application', function () {
    // A published agent under a different application is invisible to this
    // credential — tenant isolation surfaces as agent_not_found, not a leak.
    maaccAgent($this->team, ['agent_slug' => 'foreign-agent', 'status' => AgentStatus::Published]);

    try {
        $this->client->startRun('foreign-agent', 'hello');
        $this->fail('Expected a MaaccApiException for the foreign agent.');
    } catch (MaaccApiException $exception) {
        expect($exception->errorCode)->toBe('agent_not_found')
            ->and($exception->status)->toBe(404);
    }
});

it('rejects invoking an unpublished agent', function () {
    Agent::firstWhere('agent_slug', MaaccE2ESeeder::AGENT_SLUG)->update([
        'status' => AgentStatus::Draft,
    ]);

    try {
        $this->client->startRun(MaaccE2ESeeder::AGENT_SLUG, 'hello');
        $this->fail('Expected a MaaccApiException for the unpublished agent.');
    } catch (MaaccApiException $exception) {
        expect($exception->errorCode)->toBe('agent_not_published')
            ->and($exception->status)->toBe(409);
    }
});

it('raises a missing-handler error when MAACC pauses for an unregistered tool', function () {
    bindFakeRouter()->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'today']);

    expect(fn () => $this->client->run(MaaccE2ESeeder::AGENT_SLUG, 'Summarize', new ToolHandlerRegistry))
        ->toThrow(MissingToolHandlerException::class, MaaccE2ESeeder::TOOL_SLUG);
});

it('keeps a paused run resumable after an oversized tool result is rejected', function () {
    bindFakeRouter()->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'today']);

    $paused = $this->client->startRun(MaaccE2ESeeder::AGENT_SLUG, 'Summarize');

    // The seeded tool caps results at 256 KB.
    try {
        $this->client->submitToolResult($paused->runId, $paused->toolCall->id, [
            'records' => [str_repeat('x', 300 * 1024)],
            'total' => 1,
        ]);
        $this->fail('Expected a payload_too_large error.');
    } catch (MaaccApiException $exception) {
        expect($exception->errorCode)->toBe('payload_too_large')
            ->and($exception->status)->toBe(413);
    }

    // The run is still resumable with a valid result.
    expect($this->client->getRun($paused->runId)->isWaiting())->toBeTrue();
});
