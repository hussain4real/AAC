<?php

use App\Enums\MaacRole;
use App\Http\Resources\Maac\CredentialResource;
use App\Http\Resources\Maac\ToolCallResource;
use App\Http\Resources\Maac\TraceEventResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\AgentVersion;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolCall;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\TraceEvent;
use App\Models\User;
use App\Support\Slug;
use Database\Seeders\MaacDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the console serializes the full seeded dataset through the shared prop', function () {
    $this->seed(MaacDemoSeeder::class);
    $user = User::firstWhere('email', 'demo@milaha.com');
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maac.apps', 5)
            ->has('maac.projects', 8)
            ->has('maac.agents', 9)
            ->has('maac.tools', 11)
            ->has('maac.runs', 12)
            ->has('maac.llms', 7));
});

test('MAAC models expose their relationships and accessors', function () {
    [$user, $team] = ownerAndTeam();
    $app = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($app)->create(['created_by' => $user->id]);
    $llm = LlmProvider::factory()->for($team)->create();
    $project = Project::factory()->for($app)->create();
    $project->llmProviders()->attach($llm);
    $project->members()->attach($user, ['maac_role' => MaacRole::Developer->value]);
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();
    $version = AgentVersion::factory()->for($agent)->for($llm, 'llmProvider')->create(['published_by' => $user->id]);
    $agent->update(['current_version_id' => $version->id]);
    $tool = ToolContract::factory()->for($team)->for($app)->create();
    $agentAssignment = ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);
    $projectAssignment = ToolAssignment::factory()->forProject($project)->create(['tool_contract_id' => $tool->id]);
    $implementation = ToolImplementation::factory()->for($tool)->for($app)->create();
    $run = AgentRun::factory()->for($agent)->for($project)->for($app)->for($llm, 'llmProvider')->create();
    $toolCall = ToolCall::factory()->for($run)->for($tool)->create();
    $trace = TraceEvent::factory()->for($run)->create();
    $audit = AuditEvent::factory()->for($team)->create([
        'actor_user_id' => $user->id,
        'auditable_type' => Application::class,
        'auditable_id' => $app->id,
    ]);

    expect($team->applications)->toHaveCount(1)
        ->and($team->llmProviders)->toHaveCount(1)
        ->and($team->toolContracts)->toHaveCount(1)
        ->and($team->auditEvents)->not->toBeEmpty()
        ->and($app->team->id)->toBe($team->id)
        ->and($app->projects)->toHaveCount(1)
        ->and($app->credentials)->toHaveCount(1)
        ->and($app->ownedTools)->toHaveCount(1)
        ->and($app->toolImplementations)->toHaveCount(1)
        ->and($app->agents)->toHaveCount(1)
        ->and($app->runs)->toHaveCount(1)
        ->and($app->credentialStatus())->toBe('Active')
        ->and($credential->application->id)->toBe($app->id)
        ->and($credential->creator->id)->toBe($user->id)
        ->and($credential->isUsable())->toBeTrue()
        ->and($llm->team->id)->toBe($team->id)
        ->and($llm->projects)->toHaveCount(1)
        ->and($llm->agents)->toHaveCount(1)
        ->and($project->application->id)->toBe($app->id)
        ->and($project->llmProviders)->toHaveCount(1)
        ->and($project->agents)->toHaveCount(1)
        ->and($project->members)->toHaveCount(1)
        ->and($project->projectMembers)->toHaveCount(1)
        ->and($agent->project->id)->toBe($project->id)
        ->and($agent->llmProvider->id)->toBe($llm->id)
        ->and($agent->currentVersion->id)->toBe($version->id)
        ->and($agent->versions)->toHaveCount(1)
        ->and($agent->tools)->toHaveCount(1)
        ->and($agent->runs)->toHaveCount(1)
        ->and($agent->application()->id)->toBe($app->id)
        ->and($version->agent->id)->toBe($agent->id)
        ->and($version->llmProvider->id)->toBe($llm->id)
        ->and($version->publisher->id)->toBe($user->id)
        ->and($tool->team->id)->toBe($team->id)
        ->and($tool->application->id)->toBe($app->id)
        ->and($tool->assignments)->toHaveCount(2)
        ->and($tool->implementations)->toHaveCount(1)
        ->and($tool->agents)->toHaveCount(1)
        ->and($tool->toolCalls)->toHaveCount(1)
        ->and($tool->ownerLabel())->toBe($app->slug)
        ->and($agentAssignment->toolContract->id)->toBe($tool->id)
        ->and($agentAssignment->agent->id)->toBe($agent->id)
        ->and($projectAssignment->project->id)->toBe($project->id)
        ->and($implementation->toolContract->id)->toBe($tool->id)
        ->and($implementation->application->id)->toBe($app->id)
        ->and($run->agent->id)->toBe($agent->id)
        ->and($run->project->id)->toBe($project->id)
        ->and($run->application->id)->toBe($app->id)
        ->and($run->llmProvider->id)->toBe($llm->id)
        ->and($run->toolCalls)->toHaveCount(1)
        ->and($run->traceEvents)->toHaveCount(1)
        ->and($run->getRouteKeyName())->toBe('slug')
        ->and($toolCall->agentRun->id)->toBe($run->id)
        ->and($toolCall->toolContract->id)->toBe($tool->id)
        ->and($trace->agentRun->id)->toBe($run->id)
        ->and($audit->team->id)->toBe($team->id)
        ->and($audit->actor->id)->toBe($user->id)
        ->and($audit->auditable->id)->toBe($app->id);

    $member = $project->projectMembers->first();
    expect($member->project->id)->toBe($project->id)
        ->and($member->user->id)->toBe($user->id);

    // Owner label falls back to "Platform" for global (application-less) tools.
    $globalTool = ToolContract::factory()->for($team)->global()->create();
    expect($globalTool->ownerLabel())->toBe('Platform');

    // A revoked credential makes the application's status "Revoked".
    Credential::factory()->for($app)->revoked()->create();
    $app->load('credentials');
    expect($app->credentialStatus())->toBe('Active'); // still has the active one
});

test('the standalone MAAC resources serialize their records', function () {
    [, $team] = ownerAndTeam();
    $app = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($app)->create();
    $llm = LlmProvider::factory()->for($team)->create();
    $project = Project::factory()->for($app)->create();
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();
    $run = AgentRun::factory()->for($agent)->for($project)->for($app)->for($llm, 'llmProvider')->create();
    $toolCall = ToolCall::factory()->for($run)->create(['tool_contract_id' => null, 'execution_mode' => null]);
    $trace = TraceEvent::factory()->for($run)->create(['occurred_at' => null]);

    expect((new CredentialResource($credential))->resolve())
        ->toHaveKeys(['id', 'environment', 'clientId', 'lastFour', 'status'])
        ->and((new ToolCallResource($toolCall))->resolve())
        ->toHaveKeys(['id', 'toolName', 'status', 'execMode'])
        ->and((new TraceEventResource($trace))->resolve())
        ->toHaveKeys(['id', 'type', 'label', 'occurredAt']);
});

test('Slug::unique appends a numeric suffix on collision', function () {
    [, $team] = ownerAndTeam();
    Application::factory()->for($team)->create(['slug' => 'duplicate']);

    expect(Slug::unique('applications', 'duplicate'))->toBe('duplicate-2');
});

test('every MAAC policy ability resolves for an authorized owner', function () {
    [$owner, $team] = ownerAndTeam();
    $app = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($app)->create();
    $llm = LlmProvider::factory()->for($team)->create();
    $project = Project::factory()->for($app)->create();
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();
    $tool = ToolContract::factory()->for($team)->create();

    // Application
    expect($owner->can('viewAny', Application::class))->toBeTrue()
        ->and($owner->can('view', $app))->toBeTrue()
        ->and($owner->can('create', Application::class))->toBeTrue()
        ->and($owner->can('update', $app))->toBeTrue()
        ->and($owner->can('delete', $app))->toBeTrue();

    // Credential
    expect($owner->can('view', $credential))->toBeTrue()
        ->and($owner->can('create', [Credential::class, $app]))->toBeTrue()
        ->and($owner->can('rotate', $credential))->toBeTrue()
        ->and($owner->can('revoke', $credential))->toBeTrue();

    // LLM provider
    expect($owner->can('viewAny', LlmProvider::class))->toBeTrue()
        ->and($owner->can('view', $llm))->toBeTrue()
        ->and($owner->can('create', LlmProvider::class))->toBeTrue()
        ->and($owner->can('update', $llm))->toBeTrue()
        ->and($owner->can('delete', $llm))->toBeTrue();

    // Project
    expect($owner->can('viewAny', Project::class))->toBeTrue()
        ->and($owner->can('view', $project))->toBeTrue()
        ->and($owner->can('create', Project::class))->toBeTrue()
        ->and($owner->can('update', $project))->toBeTrue()
        ->and($owner->can('delete', $project))->toBeTrue();

    // Agent (create both with an explicit project and without)
    expect($owner->can('viewAny', Agent::class))->toBeTrue()
        ->and($owner->can('view', $agent))->toBeTrue()
        ->and($owner->can('create', Agent::class))->toBeTrue()
        ->and($owner->can('create', [Agent::class, $project]))->toBeTrue()
        ->and($owner->can('update', $agent))->toBeTrue()
        ->and($owner->can('publish', $agent))->toBeTrue()
        ->and($owner->can('delete', $agent))->toBeTrue();

    // Tool contract
    expect($owner->can('viewAny', ToolContract::class))->toBeTrue()
        ->and($owner->can('view', $tool))->toBeTrue()
        ->and($owner->can('create', ToolContract::class))->toBeTrue()
        ->and($owner->can('update', $tool))->toBeTrue()
        ->and($owner->can('approve', $tool))->toBeTrue()
        ->and($owner->can('delete', $tool))->toBeTrue();
});

test('a plain member is denied management abilities across resources', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $app = Application::factory()->for($team)->create();
    $llm = LlmProvider::factory()->for($team)->create();
    $project = Project::factory()->for($app)->create();
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();
    $tool = ToolContract::factory()->for($team)->create();
    $credential = Credential::factory()->for($app)->create();

    expect($member->can('update', $app))->toBeFalse()
        ->and($member->can('rotate', $credential))->toBeFalse()
        ->and($member->can('update', $llm))->toBeFalse()
        ->and($member->can('update', $project))->toBeFalse()
        ->and($member->can('publish', $agent))->toBeFalse()
        ->and($member->can('approve', $tool))->toBeFalse()
        ->and($member->can('create', [Agent::class, $project]))->toBeFalse();
});
