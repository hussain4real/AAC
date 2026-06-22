<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Models\Application;
use App\Models\Credential;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create([
        'environment' => Environment::Production,
    ]);
    $this->tool = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'getOperationalRecords',
        'execution_mode' => ExecMode::Client,
        'version' => '2.0.0',
    ]);
    $this->credential = Credential::factory()->for($this->application)->withOauthClient()->create([
        'environment' => Environment::Production,
    ]);

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
});

function reportImpl(array $implementations): TestResponse
{
    return test()->postJson('/api/v1/tool-implementations', ['implementations' => $implementations]);
}

test('reporting a current handler marks it implemented and persists the record', function () {
    $response = reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'OpsRecordsHandler',
        'version' => '2.0.0',
        'schema_fingerprint' => $this->tool->schemaFingerprint(),
        'language' => 'typescript',
    ]]);

    $response->assertOk()
        ->assertJsonPath('results.0.accepted', true)
        ->assertJsonPath('results.0.status', 'implemented');

    $this->assertDatabaseHas('tool_implementations', [
        'tool_contract_id' => $this->tool->id,
        'application_id' => $this->application->id,
        'environment' => 'production',
        'status' => 'implemented',
        'handler_name' => 'OpsRecordsHandler',
        'implemented_version' => '2.0.0',
        'language' => 'typescript',
    ]);
});

test('reporting a handler captures the SDK client version', function () {
    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'OpsRecordsHandler',
        'version' => '2.0.0',
        'schema_fingerprint' => $this->tool->schemaFingerprint(),
        'language' => 'php',
        'sdk_version' => '1.0.0',
    ]])->assertOk()
        ->assertJsonPath('results.0.status', 'implemented')
        ->assertJsonPath('results.0.sdk_version', '1.0.0');

    $this->assertDatabaseHas('tool_implementations', [
        'tool_contract_id' => $this->tool->id,
        'application_id' => $this->application->id,
        'sdk_version' => '1.0.0',
    ]);
});

test('reporting an older version marks it outdated', function () {
    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'OpsRecordsHandler',
        'version' => '1.0.0',
    ]])->assertOk()->assertJsonPath('results.0.status', 'outdated');
});

test('reporting a mismatched schema fingerprint marks it incompatible', function () {
    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'OpsRecordsHandler',
        'version' => '2.0.0',
        'schema_fingerprint' => 'mismatch',
    ]])->assertOk()->assertJsonPath('results.0.status', 'incompatible');
});

test('reporting an unknown tool is rejected per item', function () {
    reportImpl([[
        'tool' => 'doesNotExist',
        'handler_name' => 'X',
        'version' => '1.0.0',
    ]])->assertOk()
        ->assertJsonPath('results.0.accepted', false)
        ->assertJsonPath('results.0.error', 'tool_not_found');
});

test('reporting a tool owned by another application is not found', function () {
    $otherApp = Application::factory()->for($this->team)->create();
    ToolContract::factory()->for($this->team)->for($otherApp)->create([
        'slug' => 'foreignTool',
        'execution_mode' => ExecMode::Client,
    ]);

    reportImpl([[
        'tool' => 'foreignTool',
        'handler_name' => 'X',
        'version' => '1.0.0',
    ]])->assertOk()->assertJsonPath('results.0.error', 'tool_not_found');
});

test('reporting a non client-side tool is rejected', function () {
    ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'hostedTool',
        'execution_mode' => ExecMode::Hosted,
    ]);

    reportImpl([[
        'tool' => 'hostedTool',
        'handler_name' => 'X',
        'version' => '1.0.0',
    ]])->assertOk()->assertJsonPath('results.0.error', 'not_client_side');
});

test('reporting against a disabled contract is rejected', function () {
    $this->tool->update(['status' => 'Disabled']);

    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'X',
        'version' => '2.0.0',
    ]])->assertOk()->assertJsonPath('results.0.error', 'tool_disabled');
});

test('a malformed report fails validation', function () {
    reportImpl([['handler_name' => 'X']])->assertStatus(422);
    test()->postJson('/api/v1/tool-implementations', [])->assertStatus(422);
});

test('a repeated report updates the existing implementation record', function () {
    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'First',
        'version' => '1.0.0',
    ]])->assertOk();

    reportImpl([[
        'tool' => 'getOperationalRecords',
        'handler_name' => 'Second',
        'version' => '2.0.0',
    ]])->assertOk()->assertJsonPath('results.0.status', 'implemented');

    expect(ToolImplementation::query()
        ->where('tool_contract_id', $this->tool->id)
        ->where('environment', 'production')
        ->count())->toBe(1);
});
