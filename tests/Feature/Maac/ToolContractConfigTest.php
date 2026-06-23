<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\ExecMode;
use App\Http\Resources\Maac\ApprovalRequestResource;
use App\Http\Resources\Maac\ToolContractResource;
use App\Models\ApprovalRequest;
use App\Models\McpConnector;
use App\Models\Team;
use App\Models\ToolContract;

/**
 * Build the base tool-store payload, merged with the given overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function toolPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'remoteLookup',
        'scope' => 'project',
        'execution_mode' => 'http',
        'sensitivity' => 'internal',
        'timeout_seconds' => 15,
        'max_payload_kb' => 256,
        'input_schema' => ['q' => 'string'],
        'output_schema' => ['result' => 'string'],
    ], $overrides);
}

test('a remote HTTP tool persists its execution config', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'http_config' => [
                'method' => 'post',
                'endpoint' => 'https://api.example.com/lookup',
                'auth' => ['type' => 'bearer', 'credential' => 'tok-1'],
                'retry' => ['max_attempts' => 2, 'backoff_ms' => 100],
            ],
            'redaction' => ['ssn'],
        ]))
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'remoteLookup');

    expect($tool->execution_mode)->toBe(ExecMode::Http)
        ->and($tool->status)->toBe('Active')
        ->and($tool->httpConfig()['endpoint'])->toBe('https://api.example.com/lookup')
        ->and($tool->httpConfig()['method'])->toBe('post')
        ->and($tool->httpConfig()['auth']['credential'])->toBe('tok-1')
        ->and($tool->httpConfig()['retry']['max_attempts'])->toBe(2)
        ->and($tool->redactionPaths())->toBe(['ssn']);

    // The console-safe resource view never leaks the credential.
    $view = (new ToolContractResource($tool))->toArray(request());
    expect($view['httpConfig']['endpoint'])->toBe('https://api.example.com/lookup')
        ->and($view['httpConfig']['authType'])->toBe('bearer')
        ->and($view['httpConfig']['authConfigured'])->toBeTrue()
        ->and($view['httpConfig'])->not->toHaveKey('credential')
        ->and($view['redaction'])->toBe(['ssn']);
});

test('a remote HTTP tool requiring approval starts as draft and opens an approval', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'requires_approval' => true,
            'http_config' => [
                'method' => 'get',
                'endpoint' => 'https://api.example.com/lookup',
                'auth' => ['type' => 'none'],
            ],
            'redaction' => ['token'],
        ]))
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'remoteLookup');

    expect($tool->status)->toBe('Draft');

    $approval = ApprovalRequest::query()
        ->where('type', ApprovalType::ToolContract)
        ->where('subject_id', $tool->id)
        ->where('status', ApprovalStatus::Pending)
        ->first();

    expect($approval)->not->toBeNull();

    // The approval review surfaces the egress detail a reviewer needs.
    $fields = collect((new ApprovalRequestResource($approval))->toArray(request())['subject']['fields']);
    expect($fields->firstWhere('k', 'Endpoint')['v'])->toBe('https://api.example.com/lookup')
        ->and($fields->firstWhere('k', 'HTTP method')['v'])->toBe('GET')
        ->and($fields->firstWhere('k', 'Redacted fields')['v'])->toBe('token');
});

test('a connector tool persists its connector mapping and shows it in review', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->create(['name' => 'Ops MCP']);

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'name' => 'connectorLookup',
            'execution_mode' => 'connector',
            'requires_approval' => true,
            'mcp_connector_id' => $connector->id,
            'mcp_tool_name' => 'lookup',
        ]))
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'connectorLookup');

    expect($tool->execution_mode)->toBe(ExecMode::Connector)
        ->and($tool->mcp_connector_id)->toBe($connector->id)
        ->and($tool->mcp_tool_name)->toBe('lookup')
        ->and($tool->status)->toBe('Draft')
        ->and($tool->http_config)->toBeNull();

    $approval = ApprovalRequest::query()->where('subject_id', $tool->id)->first();
    $fields = collect((new ApprovalRequestResource($approval))->toArray(request())['subject']['fields']);
    expect($fields->firstWhere('k', 'Connector')['v'])->toBe('Ops MCP')
        ->and($fields->firstWhere('k', 'Remote tool')['v'])->toBe('lookup');
});

test('a remote HTTP tool requires a valid endpoint', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'http_config' => ['method' => 'post', 'auth' => ['type' => 'none']],
        ]))
        ->assertSessionHasErrors('http_config.endpoint');
});

test('a connector tool requires a connector and remote tool name', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'execution_mode' => 'connector',
        ]))
        ->assertSessionHasErrors(['mcp_connector_id', 'mcp_tool_name']);
});

test('a connector from another team cannot be mapped', function () {
    [$owner, $team] = ownerAndTeam();
    $foreignConnector = McpConnector::factory()->for(Team::factory())->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolPayload([
            'execution_mode' => 'connector',
            'mcp_connector_id' => $foreignConnector->id,
            'mcp_tool_name' => 'lookup',
        ]))
        ->assertSessionHasErrors('mcp_connector_id');
});

test('updating a remote HTTP tool without a credential preserves the stored one', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->remoteHttp([
        'endpoint' => 'https://api.example.com/lookup',
        'auth' => ['type' => 'bearer', 'credential' => 'original-secret'],
    ])->create();

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $team->slug, 'tool' => $tool->slug]), [
            'execution_mode' => 'http',
            'http_config' => [
                'method' => 'post',
                'endpoint' => 'https://api.example.com/v2/lookup',
                'auth' => ['type' => 'bearer', 'credential' => ''],
            ],
        ])
        ->assertRedirect();

    $fresh = $tool->fresh();
    expect($fresh->httpConfig()['endpoint'])->toBe('https://api.example.com/v2/lookup')
        ->and($fresh->httpConfig()['auth']['credential'])->toBe('original-secret');
});

test('switching a tool away from a server-side mode clears its config', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->remoteHttp()->create();

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $team->slug, 'tool' => $tool->slug]), [
            'execution_mode' => 'client',
        ])
        ->assertRedirect();

    expect($tool->fresh()->http_config)->toBeNull();
});
