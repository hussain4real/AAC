<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\RemoteAuthType;
use App\Models\McpConnector;
use App\Models\ToolContract;
use App\Support\Runtime\Mcp\McpCapabilityDiscoverer;
use App\Support\Runtime\Mcp\McpToolExecutor;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Mcp\FakeMcpServer;

function mcpExecutor(): McpToolExecutor
{
    return app(McpToolExecutor::class);
}

function connectorTool(McpConnector $connector, string $remoteTool = 'lookup', array $output = ['result' => 'string']): ToolContract
{
    return ToolContract::factory()->connector($connector, $remoteTool)->create(['output_schema' => $output]);
}

it('executes a connector tool and returns its structured content', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('lookup', ['result' => 'Doha'])->handler());

    $connector = McpConnector::factory()->create(['server_url' => 'https://mcp.example.com/mcp']);

    $result = mcpExecutor()->execute(connectorTool($connector), Environment::Production, ['q' => 'port']);

    expect($result)->toBe(['result' => 'Doha']);
    Http::assertSent(fn ($request) => str_contains($request->body(), '"method":"tools\/call"'));
});

it('falls back to text content when the connector returns no structured content', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returnsText('lookup', 'plain answer')->handler());

    $connector = McpConnector::factory()->create();

    $result = mcpExecutor()->execute(connectorTool($connector), Environment::Production, []);

    expect($result)->toBe(['result' => 'plain answer']);
});

it('sends the bearer token for an authenticated connector', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('lookup', ['result' => 'ok'])->handler());

    $connector = McpConnector::factory()->withBearer('secret-abc')->create();

    mcpExecutor()->execute(connectorTool($connector), Environment::Production, []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-abc'));
});

it('sends a custom auth header for a header-authenticated connector', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('lookup', ['result' => 'ok'])->handler());

    $connector = McpConnector::factory()->create([
        'auth_type' => RemoteAuthType::Header,
        'auth_credential' => 'key-123',
        'auth_header' => 'X-Api-Key',
    ]);

    mcpExecutor()->execute(connectorTool($connector), Environment::Production, []);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'key-123'));
});

it('fails when the tool is not mapped to a connector', function () {
    $tool = ToolContract::factory()->create(['execution_mode' => ExecMode::Connector, 'mcp_tool_name' => null]);

    expect(fn () => mcpExecutor()->execute($tool, Environment::Production, []))
        ->toThrow(ToolExecutionException::class)
        ->and(fn () => mcpExecutor()->execute($tool, Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_misconfigured'));
});

it('fails when the connector is not available in the environment', function () {
    $connector = McpConnector::factory()->disabled()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_unavailable'));
});

it('maps a 401 from the connector to a controlled unauthorized failure', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 401)]);

    $connector = McpConnector::factory()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_unauthorized'));
});

it('maps a connection failure to a controlled unreachable failure', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    $connector = McpConnector::factory()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_unreachable'));
});

it('maps a JSON-RPC error to a controlled connector failure', function () {
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        $payload = json_decode($request->body(), true) ?: [];

        return match ($payload['method'] ?? null) {
            'initialize' => Http::response(['jsonrpc' => '2.0', 'id' => $payload['id'] ?? null, 'result' => [
                'protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'serverInfo' => ['name' => 'x', 'version' => '1'],
            ]]),
            'tools/call' => Http::response(['jsonrpc' => '2.0', 'id' => $payload['id'] ?? null, 'error' => ['code' => -32000, 'message' => 'boom']]),
            default => Http::response('', 202),
        };
    });

    $connector = McpConnector::factory()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_failed'));
});

it('maps a tool-level error result to a controlled connector failure', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returnsError('lookup', 'remote exploded')->handler());

    $connector = McpConnector::factory()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_failed'));
});

it('treats an empty connector result as invalid output', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returnsEmpty('lookup')->handler());

    $connector = McpConnector::factory()->create();

    expect(fn () => mcpExecutor()->execute(connectorTool($connector), Environment::Production, []))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_invalid_output'));
});

it('maps an unreachable server during discovery to a controlled failure', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    $connector = McpConnector::factory()->create();

    expect(fn () => app(McpCapabilityDiscoverer::class)->discover($connector))
        ->toThrow(fn (ToolExecutionException $e) => expect($e->failureCode)->toBe('connector_unreachable'));
});

it('discovers and persists connector capabilities', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()
        ->withTool('lookup', ['type' => 'object'], 'Look something up')
        ->withTool('translate')
        ->handler());

    $connector = McpConnector::factory()->create();

    $capabilities = app(McpCapabilityDiscoverer::class)->discover($connector);

    expect($capabilities)->toHaveCount(2)
        ->and($capabilities[0]['name'])->toBe('lookup')
        ->and($connector->fresh()->discoveredToolNames())->toBe(['lookup', 'translate'])
        ->and($connector->fresh()->last_discovered_at)->not->toBeNull();
});
