<?php

use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\MaacRole;
use App\Enums\QuotaScope;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Http\Resources\Maac\AgentRunResource;
use App\Http\Resources\Maac\ApprovalRequestResource;
use App\Http\Resources\Maac\AuditEventResource;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Credential;
use App\Models\GovernanceSetting;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\QuotaLimit;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalManager;
use App\Support\GovernanceConsoleData;
use App\Support\MaacConsoleData;

test('the governance console dataset assembles approvals, roles, policies, quotas, and audit', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    projectRoleUser($team, $project, MaacRole::Developer);
    projectRoleUser($team, $project, MaacRole::Viewer);

    $tool = ToolContract::factory()->for($team)->create(['application_id' => $application->id]);
    $model = LlmProvider::factory()->for($team)->create();
    app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);
    app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    QuotaLimit::factory()->for($team)->create(['scope' => QuotaScope::Application, 'subject_id' => $application->id, 'environment' => Environment::Production]);
    QuotaLimit::factory()->for($team)->create(['scope' => QuotaScope::Platform, 'environment' => null]);

    AuditEvent::factory()->create(['team_id' => $team->id]);

    $data = GovernanceConsoleData::forTeam($team);

    expect($data['approvals']['tools'])->toHaveCount(1)
        ->and($data['approvals']['models'])->toHaveCount(1)
        ->and($data['approvals']['tools'][0]['app'])->toBe($application->name)
        ->and($data['approvals']['models'][0]['app'])->toBe('Platform')
        ->and($data['auditEvents'])->not->toBeEmpty()
        ->and(collect($data['roles'])->firstWhere('name', 'Platform Admin')['users'])->toBe(1)
        ->and(collect($data['roles'])->firstWhere('name', 'Developer')['users'])->toBe(1)
        ->and(collect($data['roles'])->firstWhere('name', 'Developer')['perms'])->toContain('Manage Agent')
        ->and($data['policies'])->toHaveCount(5)
        ->and($data['quotas'])->toHaveCount(2)
        ->and(collect($data['quotas'])->pluck('environment')->all())->toContain('Production', 'All')
        ->and($data['governanceSettings']['auditRetentionDays'])->toBe(365);
});

test('policies reflect the team governance settings', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create([
        'mask_sensitive_inputs' => false,
        'mask_sensitive_outputs' => false,
        'block_restricted_logging' => false,
        'default_daily_run_quota' => 500,
    ]);

    $policies = collect(GovernanceConsoleData::forTeam($team)['policies']);

    expect($policies->firstWhere('name', 'Tool result masking')['on'])->toBeFalse()
        ->and($policies->firstWhere('name', 'Restricted logging blocked')['on'])->toBeFalse()
        ->and($policies->firstWhere('name', 'Daily run quota')['on'])->toBeTrue();
});

test('the maac console prop carries the observability and governance rollups', function () {
    [, $team] = ownerAndTeam();
    maacAgent($team);

    $data = MaacConsoleData::forTeam($team);

    expect($data)->toHaveKeys(['apps', 'runs', 'dashboard', 'operational', 'approvals', 'auditEvents', 'roles', 'policies', 'governanceSettings', 'quotas'])
        ->and($data['dashboard'])->toHaveKeys(['stats', 'runStatus', 'runsOverTime', 'topAgents', 'alerts'])
        ->and($data['operational'])->toHaveKeys(['errorRate', 'waitingRuns', 'costAnomaly']);
});

test('the audit event resource describes the actor, target, and action', function () {
    [, $team] = ownerAndTeam();

    $system = AuditEvent::factory()->create(['team_id' => $team->id, 'actor_label' => null, 'auditable_type' => null, 'action' => 'agent.published']);
    $byUser = AuditEvent::factory()->create(['team_id' => $team->id, 'actor_label' => 'jane', 'auditable_type' => Application::class]);

    $systemArr = (new AuditEventResource($system))->toArray(request());
    $userArr = (new AuditEventResource($byUser))->toArray(request());

    expect($systemArr['actor'])->toBe('System')
        ->and($systemArr['target'])->toBeNull()
        ->and($systemArr['label'])->toBe('Agent Published')
        ->and($userArr['actor'])->toBe('jane')
        ->and($userArr['target'])->toBe('Application');
});

test('the agent run resource exposes sensitivity, masking, and failure reason', function () {
    [, $team] = ownerAndTeam();
    $agent = maacAgent($team);
    $run = maacRun($agent, [
        'sensitivity' => Sensitivity::Confidential,
        'masked' => true,
        'status' => RunStatus::Failed,
        'failure_reason' => 'model_error',
        'error' => 'boom',
    ]);

    $array = (new AgentRunResource($run))->toArray(request());

    expect($array['sensitivity'])->toBe('Confidential')
        ->and($array['masked'])->toBeTrue()
        ->and($array['failureReason'])->toBe('model_error');
});

test('an approval request exposes its relations', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $tool = ToolContract::factory()->for($team)->create();

    $request = ApprovalRequest::factory()->for($team)->create([
        'application_id' => $application->id,
        'project_id' => $project->id,
        'requested_by' => $owner->id,
        'decided_by' => $owner->id,
        'subject_type' => ToolContract::class,
        'subject_id' => $tool->id,
    ]);

    expect($request->team->is($team))->toBeTrue()
        ->and($request->application->is($application))->toBeTrue()
        ->and($request->project->is($project))->toBeTrue()
        ->and($request->requester->is($owner))->toBeTrue()
        ->and($request->decider->is($owner))->toBeTrue()
        ->and($request->subject->is($tool))->toBeTrue();
});

test('the approval resource builds a 360 subject view for each type', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $manager = app(ApprovalManager::class);

    $tool = ToolContract::factory()->for($team)->create([
        'application_id' => $application->id,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['results' => 'array'],
    ]);
    $toolView = (new ApprovalRequestResource(
        $manager->requestToolContractApproval($tool, $owner)->load(['subject', 'application'])
    ))->toArray(request())['subject'];

    expect($toolView['kind'])->toBe('Tool contract')
        ->and($toolView['inputSchema'])->toBe(['query' => 'string'])
        ->and($toolView['outputSchema'])->toBe(['results' => 'array'])
        ->and(collect($toolView['fields'])->pluck('k'))->toContain('Execution mode', 'Timeout');

    $agent = maacAgent($team, ['system_prompt' => 'You assist operators.']);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);
    $agentView = (new ApprovalRequestResource(
        $manager->requestAgentPublication($agent, $owner, Environment::Production)->load('subject')
    ))->toArray(request())['subject'];

    expect($agentView['kind'])->toBe('Agent')
        ->and($agentView['systemPrompt'])->toBe('You assist operators.')
        ->and($agentView['tools'])->toContain($tool->name);

    $model = LlmProvider::factory()->for($team)->create();
    $modelView = (new ApprovalRequestResource(
        $manager->requestModelAccess($model, $owner, Environment::Production)->load('subject')
    ))->toArray(request())['subject'];

    expect($modelView['kind'])->toBe('Model')
        ->and(collect($modelView['fields'])->pluck('k'))->toContain('Model code', 'Current environments');

    $credential = Credential::factory()->for($application)->create();
    $credentialView = (new ApprovalRequestResource(
        $manager->requestCredentialChange($credential, $owner, 'rotation')->load('subject')
    ))->toArray(request())['subject'];

    expect($credentialView['kind'])->toBe('Credential')
        ->and(collect($credentialView['fields'])->pluck('k'))->toContain('Application', 'Last rotated');
});

test('a credential-change approval falls back to the Platform label and omits sensitivity', function () {
    [$owner, $team] = ownerAndTeam();
    $request = ApprovalRequest::factory()->for($team)->create([
        'type' => ApprovalType::CredentialChange,
        'application_id' => null,
        'sensitivity' => null,
        'environment' => Environment::Production,
    ]);

    $array = (new ApprovalRequestResource($request))->toArray(request());

    expect($array['app'])->toBe('Platform')
        ->and($array['queue'])->toBe('data')
        ->and($array['sensitivity'])->toBeNull()
        ->and($array['env'])->toBe('Production')
        ->and($array['waiting'])->toBeString();
});
