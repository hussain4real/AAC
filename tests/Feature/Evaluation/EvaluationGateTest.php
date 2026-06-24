<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Support\Evaluation\EvaluationGate;
use App\Support\Governance\ApprovalGate;
use App\Support\Governance\ApprovalManager;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$this->owner, $this->team] = ownerAndTeam();
    $this->agent = maacAgent($this->team);
    $this->dataset = EvaluationDataset::factory()->for($this->team)->create();
});

function makeEvaluation(array $attributes): Evaluation
{
    return Evaluation::factory()->create(array_merge([
        'team_id' => test()->team->id,
        'agent_id' => test()->agent->id,
        'evaluation_dataset_id' => test()->dataset->id,
    ], $attributes));
}

it('is satisfied when the agent has no required evaluations', function () {
    makeEvaluation(['is_required' => false, 'status' => 'failed']);

    expect(app(EvaluationGate::class)->isSatisfied($this->agent))->toBeTrue()
        ->and(app(EvaluationGate::class)->blockers($this->agent))->toBe([]);
});

it('blocks while a required evaluation has not passed', function () {
    makeEvaluation(['is_required' => true, 'status' => 'failed', 'label' => 'Release gate']);

    $blockers = app(EvaluationGate::class)->blockers($this->agent);

    expect($blockers)->toHaveCount(1)
        ->and($blockers[0])->toContain('Release gate')
        ->and($blockers[0])->toContain('has not passed');
});

it('is satisfied when the required evaluation has passed', function () {
    makeEvaluation(['is_required' => true, 'status' => 'passed']);

    expect(app(EvaluationGate::class)->isSatisfied($this->agent))->toBeTrue();
});

it('considers only the latest required evaluation per dataset', function () {
    makeEvaluation(['is_required' => true, 'status' => 'failed', 'created_at' => Carbon::now()->subDays(3)]);
    makeEvaluation(['is_required' => true, 'status' => 'passed', 'created_at' => Carbon::now()->subDay()]);

    expect(app(EvaluationGate::class)->isSatisfied($this->agent))->toBeTrue();
});

it('surfaces the evaluation blocker on an agent publication approval', function () {
    makeEvaluation(['is_required' => true, 'status' => 'failed', 'label' => 'Release gate']);

    $request = app(ApprovalManager::class)->requestAgentPublication($this->agent, $this->owner, Environment::Production);
    $blockers = app(ApprovalGate::class)->blockers($request);

    expect(collect($blockers)->contains(fn (string $b): bool => str_contains($b, 'Release gate')))->toBeTrue();
});

it('blocks publishing through the controller while a required evaluation fails', function () {
    makeEvaluation(['is_required' => true, 'status' => 'failed']);

    $this->actingAs($this->owner)
        ->post(route('agents.publish', ['current_team' => $this->team->slug, 'agent' => $this->agent->slug]))
        ->assertRedirect();

    expect($this->agent->fresh()->status)->not->toBe(AgentStatus::Published);
});

it('allows publishing through the controller once the required evaluation passes', function () {
    makeEvaluation(['is_required' => true, 'status' => 'passed']);

    $this->actingAs($this->owner)
        ->post(route('agents.publish', ['current_team' => $this->team->slug, 'agent' => $this->agent->slug]))
        ->assertRedirect();

    expect($this->agent->fresh()->status)->toBe(AgentStatus::Published);
});
