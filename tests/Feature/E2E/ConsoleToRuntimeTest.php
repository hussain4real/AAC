<?php

use App\Enums\AgentStatus;
use App\Enums\RunStatus;
use App\Enums\TraceEventType;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * Phase 6A canonical end-to-end scenario: drive the authenticated Inertia
 * console to set up the full graph (application, model, project, client-side
 * tool, agent), generate a credential, then switch to the SDK/runtime API to
 * exchange a token, sync the manifest, report an implementation, invoke the
 * agent, pause for the client tool, submit the result, and complete the run —
 * verifying the trace, audit, and cost data along the way.
 */
beforeEach(function () {
    // The happy path exchanges a real client_credentials token, which needs
    // Passport signing keys (CI starts without them).
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    [$this->owner, $this->team] = ownerAndTeam();
    $this->slug = $this->team->slug;
});

/**
 * Register a resource through the console as the platform-admin owner.
 *
 * @param  array<string, mixed>  $params
 * @param  array<string, mixed>  $payload
 */
function consolePost(string $route, array $params, array $payload = []): TestResponse
{
    return test()->actingAs(test()->owner)
        ->post(route($route, [...['current_team' => test()->slug], ...$params]), $payload)
        ->assertRedirect();
}

test('the full console setup to completed agent run works end to end', function () {
    // 1. Register an application.
    consolePost('applications.store', [], [
        'name' => 'Cargo Insights',
        'code' => 'CARGO',
        'department' => 'Logistics',
        'owner_name' => 'Console Owner',
        'owner_email' => 'owner@milaha.com',
        'environment' => 'production',
    ]);
    $application = Application::firstWhere('code', 'CARGO');
    expect($application)->not->toBeNull();

    // 2. Add an approved model to the catalog.
    consolePost('llm-providers.store', [], [
        'name' => 'E2E Model',
        'code' => 'fake/e2e',
        'provider' => 'MAAC Deterministic',
        'context_window' => '128K',
        'input_cost' => 1.0,
        'output_cost' => 2.0,
        'sensitivity' => 'internal',
        'environments' => ['production'],
        'status' => 'approved',
    ]);
    $provider = LlmProvider::firstWhere('code', 'fake/e2e');

    // 3. Create a project under the application.
    consolePost('projects.store', [], [
        'application_id' => $application->id,
        'name' => 'Cargo Project',
        'environment' => 'production',
    ]);
    $project = Project::firstWhere('application_id', $application->id);

    // 4. Create a client-side tool contract owned by the application.
    consolePost('tools.store', [], [
        'name' => 'Fetch Records',
        'application_id' => $application->id,
        'scope' => 'agent',
        'execution_mode' => 'client',
        'sensitivity' => 'internal',
        'timeout_seconds' => 15,
        'max_payload_kb' => 256,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['records' => 'array', 'total' => 'number'],
    ]);
    $tool = ToolContract::firstWhere('application_id', $application->id);

    // 5. Create the agent wired to the project, model, and tool.
    consolePost('agents.store', [], [
        'project_id' => $project->id,
        'llm_provider_id' => $provider->id,
        'name' => 'Operations Agent',
        'agent_slug' => 'ops-agent',
        'system_prompt' => 'You summarize operations.',
        'temperature' => 0.2,
        'max_tokens' => 1200,
        'tool_ids' => [$tool->id],
    ]);
    $agent = Agent::firstWhere('agent_slug', 'ops-agent');
    expect($agent->tools()->pluck('tool_contracts.id')->all())->toBe([$tool->id]);

    // 6. Publish the agent so the runtime will accept invocations.
    consolePost('agents.publish', ['agent' => $agent->slug]);
    expect($agent->refresh()->status)->toBe(AgentStatus::Published);

    // 7. Generate a credential and capture the one-time secret from the flash.
    $secretFlash = consolePost('applications.credentials.store', ['application' => $application->slug], [
        'environment' => 'production',
    ])->getSession()->get('inertia.flash_data')['credentialSecret'];

    // 8. Exchange the credential for a short-lived SDK access token.
    $token = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $secretFlash['clientId'],
        'client_secret' => $secretFlash['secret'],
    ])->assertOk()->json('access_token');

    // 9. The manifest lists the agent and reports the tool as needing a handler.
    $this->withToken($token)->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('agents.0.slug', 'ops-agent')
        ->assertJsonPath('tools.0.name', $tool->slug)
        ->assertJsonPath('tools.0.implementation.status', 'required');

    // 10. The application reports a compatible local handler.
    $this->withToken($token)->postJson('/api/v1/tool-implementations', [
        'implementations' => [[
            'tool' => $tool->slug,
            'handler_name' => 'FetchRecordsHandler',
            'version' => $tool->version,
            'schema_fingerprint' => $tool->schemaFingerprint(),
            'language' => 'typescript',
        ]],
    ])->assertOk()->assertJsonPath('results.0.status', 'implemented');

    // 11. The manifest now reports the tool implemented (the key transition).
    $this->withToken($token)->getJson('/api/v1/manifest')
        ->assertOk()
        ->assertJsonPath('tools.0.implementation.status', 'implemented');

    // 12. Invoke the agent — the model requests the client tool and the run pauses.
    bindFakeRouter()
        ->toolCallThen($tool->slug, ['query' => 'today'])
        ->textThen('12 vessels are on schedule.');

    $start = $this->withToken($token)
        ->postJson("/api/v1/agents/{$agent->agent_slug}/runs", [
            'input' => 'Summarize today.',
            'caller' => 'e2e-suite',
        ])
        ->assertCreated();
    $start->assertJsonPath('status', RunStatus::WaitingForClient->value)
        ->assertJsonPath('tool_call.tool', $tool->slug)
        ->assertJsonPath('tool_call.arguments.query', 'today');
    $runId = $start->json('run_id');

    // 13. Submit the client tool result — the run resumes and completes.
    $this->withToken($token)
        ->postJson("/api/v1/runs/{$runId}/tool-results", [
            'tool_call_id' => $start->json('tool_call.id'),
            'result' => ['records' => ['a', 'b'], 'total' => 12],
        ])
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', '12 vessels are on schedule.');

    // 14. Run status retrieval reflects the completed run.
    $this->withToken($token)->getJson("/api/v1/runs/{$runId}")
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value);

    // 15. Trace, cost, and audit data prove the run is fully observable.
    $run = AgentRun::firstWhere('slug', $runId);
    expect($run->caller)->toBe('e2e-suite')
        ->and($run->tokens_in)->toBeGreaterThan(0)
        ->and($run->cost)->toBeGreaterThan(0)
        ->and($run->traceEvents()->pluck('type')->all())->toContain(
            TraceEventType::RunRequested,
            TraceEventType::ToolRequired,
            TraceEventType::ToolResultReceived,
            TraceEventType::Resumed,
            TraceEventType::Completed,
        );

    expect(AuditEvent::where('team_id', $this->team->id)->where('action', 'application.created')->exists())->toBeTrue()
        ->and(AuditEvent::where('team_id', $this->team->id)->where('action', 'credential.created')->exists())->toBeTrue()
        ->and(AuditEvent::where('team_id', $this->team->id)->where('action', 'agent.updated')->exists())->toBeTrue();
});

test('the authenticated console renders every setup screen the operator drives', function () {
    // Seed the canonical graph, then confirm each console screen the setup path
    // touches resolves for the team-scoped operator and renders its page.
    $this->seed(MaacE2ESeeder::class);
    $owner = $this->owner;
    $slug = $this->slug;

    $screens = [
        ['applications', [], 'maac/applications/index'],
        ['projects', [], 'maac/projects/index'],
        ['llm-providers', [], 'maac/llm-providers'],
        ['tools', [], 'maac/tools/index'],
        ['agents', [], 'maac/agents/index'],
        ['agents.create', [], 'maac/agents/create'],
        ['sdk', [], 'maac/sdk'],
        ['governance', [], 'maac/governance'],
        ['runs', [], 'maac/runs/index'],
    ];

    foreach ($screens as [$name, $params, $component]) {
        $this->actingAs($owner)
            ->get(route($name, [...['current_team' => $slug], ...$params]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
    }
});
