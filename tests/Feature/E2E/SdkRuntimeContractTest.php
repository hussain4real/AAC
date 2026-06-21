<?php

use App\Actions\Maac\CreateCredential;
use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Models\ToolContract;
use App\Models\User;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/**
 * Phase 6A contract-level assertions: pin the response shape of every SDK and
 * runtime API surface against the deterministic canonical scenario, so an
 * accidental change to a response envelope fails loudly here.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaacE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);
    $this->agent = Agent::firstWhere('agent_slug', MaacE2ESeeder::AGENT_SLUG);
    $this->tool = ToolContract::firstWhere('slug', MaacE2ESeeder::TOOL_SLUG);
    $this->credential = Credential::query()
        ->where('application_id', $this->application->id)
        ->where('label', MaacE2ESeeder::CREDENTIAL_LABEL)
        ->first();
});

/**
 * Authenticate the rest of the test as the seeded application's SDK client.
 */
function actAsSdk(): void
{
    Passport::actingAsClient(test()->credential->oauthClient, [], 'api');
}

/**
 * Invoke the seeded agent's runtime endpoint.
 *
 * @param  array<string, mixed>  $payload
 */
function invokeSeededAgent(array $payload = ['input' => 'Summarize today']): TestResponse
{
    return test()->postJson('/api/v1/agents/'.test()->agent->agent_slug.'/runs', $payload);
}

test('the oauth token endpoint returns the documented token envelope', function () {
    $creator = User::firstWhere('email', MaacE2ESeeder::USER_EMAIL);
    $issued = app(CreateCredential::class)
        ->handle($this->application, $creator, ['environment' => 'production']);

    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $issued->credential->client_id,
        'client_secret' => $issued->plainSecret,
    ])->assertOk();

    $response->assertJsonStructure(['token_type', 'expires_in', 'access_token'])
        ->assertJsonPath('token_type', 'Bearer');
    expect($response->json('expires_in'))->toBeLessThanOrEqual(3600);
});

test('the manifest envelope matches the SDK contract shape', function () {
    actAsSdk();

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonStructure([
            'application' => ['id', 'name', 'environment'],
            'generated_at',
            'sdk_languages',
            'agents' => [['slug', 'name', 'version', 'status', 'tools']],
            'tools' => [[
                'name', 'version', 'description', 'execution_mode', 'sensitivity',
                'requires_approval', 'timeout_seconds', 'max_payload_kb',
                'input_schema', 'output_schema', 'schema_fingerprint', 'permission',
                'used_by_agents',
                'implementation' => ['status', 'handler_name', 'implemented_version', 'last_validated_at'],
                'stubs',
            ]],
        ]);
});

test('the implementation report envelope matches the SDK contract shape', function () {
    actAsSdk();

    $this->postJson('/api/v1/tool-implementations', [
        'implementations' => [[
            'tool' => $this->tool->slug,
            'handler_name' => 'FetchRecordsHandler',
            'version' => $this->tool->version,
            'schema_fingerprint' => $this->tool->schemaFingerprint(),
            'language' => 'typescript',
        ]],
    ])
        ->assertOk()
        ->assertJsonStructure(['results' => [['tool', 'accepted', 'status']]])
        ->assertJsonPath('results.0.accepted', true)
        ->assertJsonPath('results.0.status', 'implemented');
});

test('starting a run that pauses returns the waiting envelope shape', function () {
    actAsSdk();
    bindFakeRouter()->toolCallThen($this->tool->slug, ['query' => 'today']);

    invokeSeededAgent()
        ->assertCreated()
        ->assertJsonStructure([
            'run_id', 'agent_slug', 'status',
            'usage' => ['tokens_in', 'tokens_out'],
            'cost',
            'tool_call' => ['id', 'tool', 'arguments', 'output_schema'],
        ])
        ->assertJsonPath('status', RunStatus::WaitingForClient->value);
});

test('starting a run that completes returns the completed envelope shape', function () {
    actAsSdk();
    bindFakeRouter()->textThen('All berths are clear.');

    invokeSeededAgent()
        ->assertCreated()
        ->assertJsonStructure([
            'run_id', 'agent_slug', 'status',
            'usage' => ['tokens_in', 'tokens_out'],
            'cost', 'response',
        ])
        ->assertJsonPath('status', RunStatus::Completed->value);
});

test('run status retrieval and tool-result submission match the contract shape', function () {
    actAsSdk();
    bindFakeRouter()
        ->toolCallThen($this->tool->slug, ['query' => 'today'])
        ->textThen('Resolved.');

    $start = invokeSeededAgent()->assertCreated();
    $runId = $start->json('run_id');

    // Run status retrieval (still waiting) carries the tool-call envelope.
    $this->getJson("/api/v1/runs/{$runId}")
        ->assertOk()
        ->assertJsonStructure(['run_id', 'agent_slug', 'status', 'usage', 'cost', 'tool_call'])
        ->assertJsonPath('status', RunStatus::WaitingForClient->value);

    // Submitting the result resumes and returns the completed envelope.
    $this->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['records' => ['x'], 'total' => 1],
    ])
        ->assertOk()
        ->assertJsonStructure(['run_id', 'agent_slug', 'status', 'usage', 'cost', 'response'])
        ->assertJsonPath('status', RunStatus::Completed->value);
});
