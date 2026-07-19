<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Laravel\Passport\Passport;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create([
        'environment' => Environment::Production,
    ]);
    $this->project = Project::factory()->for($this->application)->create([
        'environment' => Environment::Production,
    ]);
    $provider = LlmProvider::factory()->for($this->team)->create();
    $this->project->llmProviders()->attach($provider);
    $this->agent = Agent::factory()->for($this->project)->for($provider)->published()->create();

    $this->tool = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'getOperationalRecords',
        'name' => 'getOperationalRecords',
        'execution_mode' => ExecMode::Client,
        'version' => '1.0.0',
    ]);
    ToolAssignment::factory()->forAgent($this->agent)->create(['tool_contract_id' => $this->tool->id]);

    $this->credential = Credential::factory()->for($this->application)->withOauthClient()->create([
        'environment' => Environment::Production,
    ]);

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
});

test('the manifest describes the application, its agents and required tools', function () {
    $response = $this->getJson('/api/v1/manifest')->assertOk();

    $response->assertJsonPath('application.id', $this->application->slug)
        ->assertJsonPath('application.environment', 'production')
        ->assertJsonCount(0, 'agents')
        ->assertJsonPath('tools.0.name', 'getOperationalRecords')
        ->assertJsonPath('tools.0.version', '1.0.0')
        ->assertJsonPath('tools.0.implementation.status', 'required')
        ->assertJsonPath('tools.0.schema_dialect', 'https://maacc.dev/schema/compact/1.0')
        ->assertJsonPath('tools.0.used_by_agents.0', $this->agent->agent_slug);

    expect($response->json('tools.0.schema_fingerprint'))->toBe($this->tool->schemaFingerprint())
        ->and($response->json('tools.0.stubs'))->toHaveKeys(['typescript', 'php', 'python'])
        ->and($response->json('tools.0.input_schema'))->toBe($this->tool->input_schema)
        ->and(collect($response->json('sdk_languages'))->pluck('value')->all())
        ->toBe(['typescript', 'php', 'python']);
});

test('the manifest includes global client tools and derives required not-required and disabled states', function () {
    $global = ToolContract::factory()->for($this->team)->create([
        'application_id' => null,
        'slug' => 'global_lookup',
        'scope' => ToolScope::Global,
        'execution_mode' => ExecMode::Client,
        'status' => 'Active',
    ]);
    $unused = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'unused_lookup',
        'scope' => ToolScope::Project,
        'execution_mode' => ExecMode::Client,
        'status' => 'Active',
    ]);
    $disabled = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'disabled_lookup',
        'scope' => ToolScope::Agent,
        'execution_mode' => ExecMode::Client,
        'status' => 'Disabled',
    ]);
    ToolAssignment::factory()->forAgent($this->agent)->create(['tool_contract_id' => $disabled->id]);

    $tools = collect($this->getJson('/api/v1/manifest')->assertOk()->json('tools'))->keyBy('name');

    expect($tools[$global->slug]['implementation']['status'])->toBe('required')
        ->and($tools[$unused->slug]['implementation']['status'])->toBe('not_required')
        ->and($tools[$disabled->slug]['implementation']['status'])->toBe('disabled');
});

test('the manifest reflects a reported implementation for the environment', function () {
    ToolImplementation::factory()->create([
        'tool_contract_id' => $this->tool->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'handler_name' => 'OpsRecordsHandler',
        'implemented_version' => '1.0.0',
    ]);

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('tools.0.implementation.status', 'implemented')
        ->assertJsonPath('tools.0.implementation.handler_name', 'OpsRecordsHandler');
});

test('the manifest excludes non-client tools and other applications tools', function () {
    // Hosted tool owned by the application — not a client-side requirement.
    ToolContract::factory()->for($this->team)->for($this->application)->create([
        'execution_mode' => ExecMode::Hosted,
    ]);

    // Client tool owned by a different application.
    $otherApp = Application::factory()->for($this->team)->create();
    ToolContract::factory()->for($this->team)->for($otherApp)->create([
        'execution_mode' => ExecMode::Client,
    ]);

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonCount(1, 'tools');
});

test('the manifest only lists published agents', function () {
    Agent::factory()->for($this->project)->create(); // draft
    ToolImplementation::factory()->for($this->tool)->for($this->application)->create([
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'implemented_version' => $this->tool->version,
        'schema_fingerprint' => $this->tool->schemaFingerprint(),
    ]);
    approveCurrentAgentConfiguration($this->agent);

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonCount(1, 'agents');
});

test('the manifest excludes agents with persisted cross-tenant tool assignments', function () {
    [, $foreignTeam] = ownerAndTeam();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();
    ToolAssignment::factory()->forAgent($this->agent)->create([
        'tool_contract_id' => $foreignTool->id,
    ]);

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonCount(0, 'agents');
});

test('the manifest distinguishes client-side tools from server-side tools MAACC executes', function () {
    ToolImplementation::factory()->for($this->tool)->for($this->application)->create([
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'implemented_version' => $this->tool->version,
        'schema_fingerprint' => $this->tool->schemaFingerprint(),
    ]);
    $hosted = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'current_time',
        'name' => 'current_time',
        'execution_mode' => ExecMode::Hosted,
    ]);
    $http = ToolContract::factory()->for($this->team)->for($this->application)->remoteHttp()->create([
        'slug' => 'fleet_status',
        'name' => 'fleet_status',
    ]);
    $connector = McpConnector::factory()->for($this->team)->for($this->application)->create();
    $connectorTool = ToolContract::factory()->for($this->team)->for($this->application)->connector($connector)->create([
        'slug' => 'port_lookup',
        'name' => 'port_lookup',
    ]);

    foreach ([$hosted, $http, $connectorTool] as $tool) {
        ToolAssignment::factory()->forAgent($this->agent)->create(['tool_contract_id' => $tool->id]);
    }
    approveCurrentAgentConfiguration($this->agent);

    $response = $this->getJson('/api/v1/manifest')->assertOk();

    // Client tools the app must implement stay in `tools` + agent.tools.
    expect($response->json('agents.0.tools'))->toBe(['getOperationalRecords']);

    // Server-side tools MAACC runs are surfaced separately, tagged with their mode.
    $serverTools = collect($response->json('agents.0.server_tools'));
    expect($serverTools->pluck('execution_mode')->sort()->values()->all())
        ->toBe(['connector', 'hosted', 'http'])
        ->and($serverTools->firstWhere('name', 'fleet_status')['execution_mode'])->toBe('http');

    // Capabilities advertise which modes are client- vs MAACC-executed.
    expect($response->json('sdk.capabilities.tool_execution_modes.client_side'))->toBe(['client'])
        ->and($response->json('sdk.capabilities.tool_execution_modes.server_side'))
        ->toBe(['hosted', 'http', 'connector', 'knowledge', 'db']);
});
