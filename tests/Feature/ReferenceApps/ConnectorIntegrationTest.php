<?php

use App\Actions\Maac\CreateCredential;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\McpConnector;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\User;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Mcp\FakeMcpServer;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6E: an external reference application invokes an agent whose tool is
 * backed by a remote MCP connector. The consumer implements no handler for it —
 * MAAC executes the tool server-side through the real Laravel MCP client (the
 * remote server is faked at the HTTP boundary) — proving an MCP-backed tool runs
 * end-to-end through the public SDK from an external application context.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaacE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);
    $owner = User::firstWhere('email', MaacE2ESeeder::USER_EMAIL);

    $issued = app(CreateCredential::class)->handle($this->application, $owner, ['environment' => 'production']);
    $this->client = new MaacClient(
        new MaacConfig('', $issued->credential->client_id, $issued->plainSecret),
        new KernelTransport($this),
    );
});

it('runs an agent whose tool MAAC executes via an MCP connector', function () {
    // Register a connector and map a connector-backed tool onto the seeded agent.
    $connector = McpConnector::factory()->for($this->application->team)->create([
        'server_url' => 'https://mcp.partner.test/mcp',
        'environments' => ['production'],
    ]);
    $tool = ToolContract::factory()
        ->for($this->application->team)
        ->for($this->application)
        ->connector($connector, 'port_lookup')
        ->create([
            'slug' => 'port_status',
            'name' => 'port_status',
            'input_schema' => ['port' => 'string?'],
            'output_schema' => ['result' => 'string'],
        ]);
    $agent = Agent::firstWhere('agent_slug', MaacE2ESeeder::AGENT_SLUG);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    // The remote MCP server is faked; MAAC drives the real MCP client to it.
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('port_lookup', ['result' => 'Hamad Port clear'])->handler());

    bindFakeRouter()
        ->toolCallThen('port_status', ['port' => 'hamad'])
        ->textThen('Hamad Port is clear.');

    // The consumer registers no local handler for the connector tool.
    $run = $this->client->run(MaacE2ESeeder::AGENT_SLUG, 'Port status?', new ToolHandlerRegistry, 'cli-connector');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('Hamad Port is clear.');

    // MAAC executed the tool server-side via the connector and audited it.
    $call = AgentRun::firstWhere('slug', $run->runId)->toolCalls()->first();
    expect($call->tool_name)->toBe('port_status')
        ->and($call->execution_mode->value)->toBe('connector')
        ->and($call->result)->toBe(['result' => 'Hamad Port clear']);

    // The MCP client actually called the remote tools/call.
    Http::assertSent(fn ($request): bool => str_contains($request->body(), '"name":"port_lookup"'));
});

it('surfaces a connector failure as a controlled run failure to the external app', function () {
    $connector = McpConnector::factory()->for($this->application->team)->create([
        'server_url' => 'https://mcp.partner.test/mcp',
        'environments' => ['production'],
    ]);
    $tool = ToolContract::factory()
        ->for($this->application->team)
        ->for($this->application)
        ->connector($connector, 'port_lookup')
        ->create([
            'slug' => 'port_status',
            'name' => 'port_status',
            'input_schema' => ['port' => 'string?'],
            'output_schema' => ['result' => 'string'],
        ]);
    $agent = Agent::firstWhere('agent_slug', MaacE2ESeeder::AGENT_SLUG);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 401)]);

    bindFakeRouter()->toolCallThen('port_status', ['port' => 'hamad']);

    $run = $this->client->run(MaacE2ESeeder::AGENT_SLUG, 'Port status?', new ToolHandlerRegistry, 'cli-connector-fail');

    expect($run->status)->toBe('failed');
    expect(AgentRun::firstWhere('slug', $run->runId)->failure_reason)->toBe('connector_unauthorized');
});
