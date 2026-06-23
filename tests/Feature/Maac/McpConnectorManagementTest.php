<?php

use App\Enums\McpConnectorStatus;
use App\Enums\RemoteAuthType;
use App\Models\McpConnector;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Mcp\FakeMcpServer;

test('the connectors console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('connectors', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maac/connectors'));
});

test('a platform admin can register an MCP connector', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('connectors.store', ['current_team' => $team->slug]), [
            'name' => 'Logistics MCP',
            'server_url' => 'https://mcp.example.com/mcp',
            'auth_type' => 'bearer',
            'auth_credential' => 'secret-token-1',
            'sensitivity' => 'internal',
            'environments' => ['production', 'staging'],
        ])
        ->assertRedirect();

    $connector = McpConnector::firstWhere('name', 'Logistics MCP');

    expect($connector)->not->toBeNull()
        ->and($connector->team_id)->toBe($team->id)
        ->and($connector->status)->toBe(McpConnectorStatus::Active)
        ->and($connector->transport)->toBe('http')
        ->and($connector->auth_type)->toBe(RemoteAuthType::Bearer)
        ->and($connector->auth_credential)->toBe('secret-token-1')
        ->and($connector->environments)->toBe(['production', 'staging'])
        ->and($connector->creator->is($owner))->toBeTrue();

    // The encrypted credential is not stored in plaintext.
    expect($connector->getRawOriginal('auth_credential'))->not->toBe('secret-token-1');
});

test('connector registration validates the server URL and header auth', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('connectors.store', ['current_team' => $team->slug]), [
            'name' => 'Bad',
            'server_url' => 'not-a-url',
            'auth_type' => 'header',
            'sensitivity' => 'internal',
            'environments' => ['production'],
        ])
        ->assertSessionHasErrors(['server_url', 'auth_credential', 'auth_header']);
});

test('a plain member cannot register a connector', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('connectors.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'server_url' => 'https://mcp.example.com/mcp',
            'auth_type' => 'none',
            'sensitivity' => 'internal',
            'environments' => ['production'],
        ])
        ->assertForbidden();
});

test('updating a connector without a credential preserves the stored one', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->withBearer('original-secret')->create();

    $this->actingAs($owner)
        ->put(route('connectors.update', ['current_team' => $team->slug, 'mcpConnector' => $connector->slug]), [
            'name' => 'Renamed Connector',
            'auth_credential' => '',
        ])
        ->assertRedirect();

    $fresh = $connector->fresh();

    expect($fresh->name)->toBe('Renamed Connector')
        ->and($fresh->auth_credential)->toBe('original-secret');
});

test('a connector can be disabled and re-enabled', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('connectors.update', ['current_team' => $team->slug, 'mcpConnector' => $connector->slug]), [
            'status' => 'disabled',
        ])
        ->assertRedirect();

    expect($connector->fresh()->status)->toBe(McpConnectorStatus::Disabled);
});

test('discovery fetches and persists the connector capabilities', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->create();

    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->withTool('lookup')->withTool('translate')->handler());

    $this->actingAs($owner)
        ->post(route('connectors.discover', ['current_team' => $team->slug, 'mcpConnector' => $connector->slug]))
        ->assertRedirect();

    expect($connector->fresh()->discoveredToolNames())->toBe(['lookup', 'translate']);
});

test('a discovery failure surfaces a controlled error and stores nothing', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->create();

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 401)]);

    $this->actingAs($owner)
        ->post(route('connectors.discover', ['current_team' => $team->slug, 'mcpConnector' => $connector->slug]))
        ->assertRedirect();

    expect($connector->fresh()->capabilities)->toBeNull();
});

test('a connector can be deleted', function () {
    [$owner, $team] = ownerAndTeam();
    $connector = McpConnector::factory()->for($team)->create();

    $this->actingAs($owner)
        ->delete(route('connectors.destroy', ['current_team' => $team->slug, 'mcpConnector' => $connector->slug]))
        ->assertRedirect();

    expect($connector->fresh()->trashed())->toBeTrue();
});

test('the console connectors dataset exposes connectors without credential material', function () {
    [$owner, $team] = ownerAndTeam();
    McpConnector::factory()->for($team)->withBearer('top-secret')->withCapabilities([
        ['name' => 'lookup', 'title' => 'Lookup', 'description' => 'Look up', 'input_schema' => []],
    ])->create(['name' => 'Ops MCP']);

    // The connectors dataset rides the shared `maac` prop served on every console
    // page, so it is asserted from an existing page to avoid a Vite build here.
    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maac.connectors', 1)
            ->where('maac.connectors.0.name', 'Ops MCP')
            ->where('maac.connectors.0.authConfigured', true)
            ->where('maac.connectors.0.authType', 'bearer')
            ->where('maac.connectors.0.toolCount', 0)
            ->where('maac.connectors.0.capabilities.0.name', 'lookup')
            ->missing('maac.connectors.0.auth_credential')
            ->missing('maac.connectors.0.authCredential'));
});
