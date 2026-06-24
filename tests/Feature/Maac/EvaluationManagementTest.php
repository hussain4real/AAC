<?php

use App\Enums\EvaluationCaseKind;
use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use Inertia\Testing\AssertableInertia as Assert;

test('the evaluation lab page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('evaluations', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maac/evaluations'));
});

test('a platform admin creates, updates, and deletes a dataset', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('evaluation-datasets.store', ['current_team' => $team->slug]), [
            'name' => 'Release Gate',
            'description' => 'Pre-promotion checks.',
        ])
        ->assertRedirect();

    $dataset = EvaluationDataset::firstWhere('name', 'Release Gate');
    expect($dataset)->not->toBeNull()->and($dataset->team_id)->toBe($team->id);

    $this->actingAs($owner)
        ->put(route('evaluation-datasets.update', ['current_team' => $team->slug, 'evaluationDataset' => $dataset->slug]), [
            'name' => 'Release Gate v2',
        ])
        ->assertRedirect();

    expect($dataset->fresh()->name)->toBe('Release Gate v2');

    $this->actingAs($owner)
        ->delete(route('evaluation-datasets.destroy', ['current_team' => $team->slug, 'evaluationDataset' => $dataset->slug]))
        ->assertRedirect();

    expect($dataset->fresh()->trashed())->toBeTrue();
});

test('a plain member cannot create a dataset', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('evaluation-datasets.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
        ])
        ->assertForbidden();
});

test('a case is added with normalized expectations and removed', function () {
    [$owner, $team] = ownerAndTeam();
    $dataset = EvaluationDataset::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('evaluation-cases.store', ['current_team' => $team->slug]), [
            'evaluation_dataset_id' => $dataset->id,
            'name' => 'Cites a policy',
            'kind' => 'rag',
            'input' => 'What is the berth policy?',
            'expectations' => [
                'expected_contains' => ['policy', 'berth'],
                'expected_tool' => 'searchPolicy',
                'forbidden_phrases' => ['password'],
                'expects_citation' => true,
                'max_cost' => '0.5',
                'max_latency_ms' => '2000',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $case = $dataset->cases()->firstWhere('name', 'Cites a policy');

    expect($case->kind)->toBe(EvaluationCaseKind::Rag)
        ->and($case->expectations['expected_contains'])->toBe(['policy', 'berth'])
        ->and($case->expectations['expected_tool'])->toBe('searchPolicy')
        ->and($case->expectations['expects_citation'])->toBeTrue()
        ->and($case->expectations['max_cost'])->toBe(0.5)
        ->and($case->expectations['max_latency_ms'])->toBe(2000)
        ->and($case->ordinal)->toBe(1);

    $this->actingAs($owner)
        ->delete(route('evaluation-cases.destroy', ['current_team' => $team->slug, 'evaluationCase' => $case->id]))
        ->assertRedirect();

    expect(EvaluationCase::find($case->id))->toBeNull();
});

test('running an evaluation records a result and gates promotion', function () {
    [$owner, $team] = ownerAndTeam();
    bindFakeRouter()->textThen('Twelve vessels are on schedule.');

    $agent = maacAgent($team);
    $dataset = EvaluationDataset::factory()->for($team)->create();
    EvaluationCase::factory()->for($dataset, 'dataset')->expects(['vessels'])->create();

    $this->actingAs($owner)
        ->post(route('evaluations.store', ['current_team' => $team->slug]), [
            'evaluation_dataset_id' => $dataset->id,
            'agent_id' => $agent->id,
            'environment' => 'production',
            'is_required' => true,
        ])
        ->assertRedirect();

    $evaluation = Evaluation::firstWhere('agent_id', $agent->id);

    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->is_required)->toBeTrue()
        ->and($evaluation->results)->toHaveCount(1);
});

test('an evaluation can be deleted', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maacAgent($team);
    $evaluation = Evaluation::factory()->create([
        'team_id' => $team->id,
        'agent_id' => $agent->id,
        'evaluation_dataset_id' => EvaluationDataset::factory()->for($team)->create()->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('evaluations.destroy', ['current_team' => $team->slug, 'evaluation' => $evaluation->id]))
        ->assertRedirect();

    expect(Evaluation::find($evaluation->id))->toBeNull();
});

test('the shared maac prop exposes datasets and evaluations', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maacAgent($team);
    $dataset = EvaluationDataset::factory()->for($team)->create(['name' => 'Gate']);
    EvaluationCase::factory()->for($dataset, 'dataset')->create();
    Evaluation::factory()->passed()->create([
        'team_id' => $team->id,
        'agent_id' => $agent->id,
        'evaluation_dataset_id' => $dataset->id,
        'label' => 'Gate run',
    ]);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maac.evaluationDatasets', 1)
            ->where('maac.evaluationDatasets.0.name', 'Gate')
            ->has('maac.evaluations', 1)
            ->where('maac.evaluations.0.label', 'Gate run')
            ->where('maac.evaluations.0.status', 'passed'));
});
