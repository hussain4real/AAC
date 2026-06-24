<?php

use App\Enums\AgentStatus;
use App\Models\AgentVersion;
use App\Models\Evaluation;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use App\Models\EvaluationResult;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Policies\EvaluationDatasetPolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\KnowledgeSourcePolicy;

test('the new policies expose viewAny and view baselines', function () {
    [$owner, $team] = ownerAndTeam();
    $outsider = User::factory()->create();

    $source = KnowledgeSource::factory()->for($team)->create();
    $dataset = EvaluationDataset::factory()->for($team)->create();
    $agent = maacAgent($team);
    $evaluation = Evaluation::factory()->create([
        'team_id' => $team->id,
        'agent_id' => $agent->id,
        'evaluation_dataset_id' => $dataset->id,
    ]);

    $knowledge = new KnowledgeSourcePolicy;
    $datasets = new EvaluationDatasetPolicy;
    $evaluations = new EvaluationPolicy;

    expect($knowledge->viewAny($owner))->toBeTrue()
        ->and($knowledge->view($owner, $source))->toBeTrue()
        ->and($knowledge->view($outsider, $source))->toBeFalse()
        ->and($datasets->viewAny($owner))->toBeTrue()
        ->and($datasets->view($owner, $dataset))->toBeTrue()
        ->and($datasets->view($outsider, $dataset))->toBeFalse()
        ->and($evaluations->viewAny($owner))->toBeTrue()
        ->and($evaluations->view($owner, $evaluation))->toBeTrue()
        ->and($evaluations->view($outsider, $evaluation))->toBeFalse();
});

test('the Phase 6F model relationships resolve their related records', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maacAgent($team);
    $project = $agent->project;

    $version = AgentVersion::query()->create([
        'agent_id' => $agent->id,
        'version' => 'v2',
        'system_prompt' => 'snapshot',
        'llm_provider_id' => $agent->llm_provider_id,
        'temperature' => 0.2,
        'max_tokens' => 1000,
        'settings' => [],
        'status' => AgentStatus::Published->value,
        'published_at' => now(),
        'published_by' => $owner->id,
    ]);

    $dataset = EvaluationDataset::factory()->for($team)->create(['created_by' => $owner->id, 'project_id' => $project->id]);
    $case = EvaluationCase::factory()->for($dataset, 'dataset')->create();
    $evaluation = Evaluation::factory()->create([
        'team_id' => $team->id,
        'agent_id' => $agent->id,
        'evaluation_dataset_id' => $dataset->id,
        'agent_version_id' => $version->id,
        'created_by' => $owner->id,
    ]);
    $run = maacRun($agent, ['evaluation_id' => $evaluation->id]);
    $result = EvaluationResult::factory()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_case_id' => $case->id,
        'agent_run_id' => $run->id,
    ]);

    $source = KnowledgeSource::factory()->for($team)->create();
    $document = KnowledgeDocument::factory()->for($source, 'source')->create();
    $chunk = KnowledgeChunk::factory()->create([
        'knowledge_source_id' => $source->id,
        'knowledge_document_id' => $document->id,
    ]);

    expect($run->evaluation->is($evaluation))->toBeTrue()
        ->and($evaluation->agentVersion->is($version))->toBeTrue()
        ->and($evaluation->creator->is($owner))->toBeTrue()
        ->and($evaluation->team->is($team))->toBeTrue()
        ->and($evaluation->dataset->is($dataset))->toBeTrue()
        ->and($evaluation->agent->is($agent))->toBeTrue()
        ->and($dataset->creator->is($owner))->toBeTrue()
        ->and($dataset->evaluations->pluck('id'))->toContain($evaluation->id)
        ->and($result->case->is($case))->toBeTrue()
        ->and($result->run->is($run))->toBeTrue()
        ->and($result->evaluation->is($evaluation))->toBeTrue()
        ->and($chunk->source->is($source))->toBeTrue()
        ->and($chunk->document->is($document))->toBeTrue()
        ->and($project->evaluationDatasets->pluck('id'))->toContain($dataset->id)
        ->and($agent->evaluations->pluck('id'))->toContain($evaluation->id)
        ->and($case->toolStub('missing'))->toBeNull();
});
