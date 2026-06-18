<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
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
    $this->project = Project::factory()->for($this->application)->create();
    $this->agent = Agent::factory()->for($this->project)->published()->create();

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
        ->assertJsonPath('agents.0.slug', $this->agent->agent_slug)
        ->assertJsonPath('tools.0.name', 'getOperationalRecords')
        ->assertJsonPath('tools.0.version', '1.0.0')
        ->assertJsonPath('tools.0.implementation.status', 'required')
        ->assertJsonPath('tools.0.used_by_agents.0', $this->agent->agent_slug);

    expect($response->json('tools.0.schema_fingerprint'))->toBe($this->tool->schemaFingerprint())
        ->and($response->json('tools.0.stubs'))->toHaveKeys(['typescript', 'php', 'python'])
        ->and($response->json('tools.0.input_schema'))->toBe($this->tool->input_schema)
        ->and(collect($response->json('sdk_languages'))->pluck('value')->all())
        ->toBe(['typescript', 'php', 'python']);
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

    $this->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonCount(1, 'agents');
});
