<?php

use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\GovernanceSetting;
use App\Models\Team;
use App\Support\Runtime\AgentRunner;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Start a run for a runtime-approval-gated agent and return [run, approval].
 *
 * @return array{0: AgentRun, 1: ApprovalRequest}
 */
function gatedRun(Team $team): array
{
    $agent = maaccAgent($team, [
        'status' => AgentStatus::Published,
        'sensitivity' => Sensitivity::Internal,
        'requires_runtime_approval' => true,
    ]);

    bindFakeRouter()->textThen('Approved work done.');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'do it', null);
    $approval = ApprovalRequest::where('subject_id', $run->id)->where('type', ApprovalType::RuntimeAction)->firstOrFail();

    return [$run, $approval];
}

test('a flagged agent pauses every run for human approval before executing', function () {
    [, $team] = ownerAndTeam();
    [$run, $approval] = gatedRun($team);

    expect($run->status)->toBe(RunStatus::RequiresApproval)
        ->and($run->traceEvents()->where('type', TraceEventType::RequiresApproval)->exists())->toBeTrue()
        ->and($run->traceEvents()->where('type', TraceEventType::ModelSelected)->exists())->toBeTrue()
        // No model turn happened — the run paused before any completion.
        ->and($run->tokens_in)->toBe(0)
        ->and($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->application_id)->toBe($run->application_id);
});

test('a run at or above the team sensitivity threshold requires approval', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::create(['team_id' => $team->id, 'runtime_approval_sensitivity' => Sensitivity::Restricted->value]);

    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Restricted]);
    bindFakeRouter()->textThen('done');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'go', null);

    expect($run->status)->toBe(RunStatus::RequiresApproval)
        ->and(ApprovalRequest::where('subject_id', $run->id)->where('type', ApprovalType::RuntimeAction)->exists())->toBeTrue();
});

test('a run below the threshold and without a flag runs normally', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::create(['team_id' => $team->id, 'runtime_approval_sensitivity' => Sensitivity::Restricted->value]);

    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Internal]);
    bindFakeRouter()->textThen('all done');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'go', null);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->traceEvents()->where('type', TraceEventType::RequiresApproval)->exists())->toBeFalse();
});

test('approving a runtime request resumes the run to completion', function () {
    [$owner, $team] = ownerAndTeam();
    [$run, $approval] = gatedRun($team);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $approval->id]))
        ->assertRedirect();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('Approved work done.')
        ->and($run->traceEvents()->where('type', TraceEventType::ApprovalGranted)->exists())->toBeTrue()
        ->and($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

test('rejecting a runtime request fails the run', function () {
    [$owner, $team] = ownerAndTeam();
    [$run, $approval] = gatedRun($team);

    $this->actingAs($owner)
        ->post(route('approvals.reject', ['current_team' => $team->slug, 'approvalRequest' => $approval->id]), ['note' => 'Too risky'])
        ->assertRedirect();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('approval_denied')
        ->and($run->traceEvents()->where('type', TraceEventType::ApprovalDenied)->exists())->toBeTrue();
});

test('a runtime approval cannot be requested manually from the console', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => 'runtime_action',
            'subject' => 'whatever',
        ])
        ->assertStatus(422);
});

test('the console approvals dataset surfaces the runtime queue with run detail', function () {
    [$owner, $team] = ownerAndTeam();
    [$run] = gatedRun($team);

    $this->actingAs($owner)
        ->get(route('governance', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.approvals.runtime', 1)
            ->where('maacc.approvals.runtime.0.subject.kind', 'Run')
            ->where('maacc.approvals.runtime.0.subject.description', $run->input));
});
