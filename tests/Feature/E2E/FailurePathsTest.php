<?php

use App\Enums\AgentStatus;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/**
 * Phase 6A controlled failure matrix: every documented failure mode of the
 * SDK/runtime surfaces fails safely against the deterministic canonical
 * scenario, returning a controlled error envelope rather than a 500.
 */
beforeEach(function () {
    $this->seed(MaacE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);
    $this->team = $this->application->team;
    $this->agent = Agent::firstWhere('agent_slug', MaacE2ESeeder::AGENT_SLUG);
    $this->tool = ToolContract::firstWhere('slug', MaacE2ESeeder::TOOL_SLUG);
    $this->credential = Credential::query()
        ->where('application_id', $this->application->id)
        ->where('label', MaacE2ESeeder::CREDENTIAL_LABEL)
        ->first();
});

/**
 * Start a run for the seeded agent as the given (or seeded) SDK client.
 *
 * @param  array<string, mixed>  $payload
 */
function startSeededRun(array $payload = ['input' => 'Summarize today']): TestResponse
{
    return test()->postJson('/api/v1/agents/'.test()->agent->agent_slug.'/runs', $payload);
}

test('a revoked credential cannot authenticate to the SDK', function () {
    $this->credential->forceFill([
        'status' => CredentialStatus::Revoked,
        'revoked_at' => now(),
    ])->save();

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');

    $this->getJson('/api/v1/manifest')
        ->assertForbidden()
        ->assertJsonPath('error', 'credential_revoked');
});

test('invoking from the wrong environment fails because the model is not approved there', function () {
    // The model is approved only in production; a staging credential's run finds
    // no approved model in its environment.
    $staging = Credential::factory()->for($this->application)->withOauthClient()->create([
        'environment' => Environment::Staging,
    ]);
    Passport::actingAsClient($staging->oauthClient, [], 'api');
    bindFakeRouter()->textThen('unused');

    startSeededRun()
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'not approved'));
});

test('invoking an unpublished agent is rejected', function () {
    $this->agent->update(['status' => AgentStatus::Draft]);
    Passport::actingAsClient($this->credential->oauthClient, [], 'api');

    startSeededRun()
        ->assertStatus(409)
        ->assertJsonPath('error', 'agent_not_published');
});

test('a missing hosted tool handler fails the run safely', function () {
    $hosted = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'e2e-hosted-missing',
        'name' => 'E2E Hosted Missing',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['note' => 'string?'],
        'output_schema' => ['ok' => 'boolean'],
    ]);
    ToolAssignment::factory()->forAgent($this->agent)->create(['tool_contract_id' => $hosted->id]);

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
    bindFakeRouter()->toolCallThen('e2e-hosted-missing', []);

    startSeededRun()
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'No hosted handler'));
});

test('an implementation reported against a different schema is marked incompatible', function () {
    Passport::actingAsClient($this->credential->oauthClient, [], 'api');

    $this->postJson('/api/v1/tool-implementations', [
        'implementations' => [[
            'tool' => $this->tool->slug,
            'handler_name' => 'MismatchedHandler',
            'version' => $this->tool->version,
            'schema_fingerprint' => 'not-the-real-fingerprint',
            'language' => 'typescript',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('results.0.accepted', true)
        ->assertJsonPath('results.0.status', 'incompatible');
});

test('an oversized tool result is rejected and the run stays resumable', function () {
    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
    bindFakeRouter()->toolCallThen($this->tool->slug, ['query' => 'today']);

    $start = startSeededRun()->assertCreated();

    $this->postJson("/api/v1/runs/{$start->json('run_id')}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        // The seeded tool caps results at 256 KB.
        'result' => ['records' => [str_repeat('x', 300 * 1024)], 'total' => 1],
    ])
        ->assertStatus(413)
        ->assertJsonPath('error', 'payload_too_large');

    expect($this->agent->runs()->first()->status)->toBe(RunStatus::WaitingForClient);
});

test('a run started past its deadline expires immediately', function () {
    config(['maac.runtime.default_timeout_seconds' => -10]);
    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
    bindFakeRouter()->textThen('too late');

    startSeededRun()
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Expired->value);
});
