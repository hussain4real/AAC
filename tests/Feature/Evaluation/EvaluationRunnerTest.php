<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\EvaluationCaseKind;
use App\Enums\EvaluationStatus;
use App\Enums\ExecMode;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\RunStatus;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Application;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Evaluation\EvaluationRunner;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;

beforeEach(function () {
    [$this->owner, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $this->project = Project::factory()->for($this->application)->create(['environment' => Environment::Production]);
    $this->model = LlmProvider::factory()->for($this->team)->create([
        'environments' => [Environment::Production->value],
        'input_cost' => 1.0,
        'output_cost' => 2.0,
    ]);
});

function evalAgent(array $attributes = [], bool $published = true): Agent
{
    $factory = Agent::factory()->for(test()->project)->for(test()->model);
    $factory = $published ? $factory->published() : $factory;

    return $factory->create($attributes);
}

function evalDataset(): EvaluationDataset
{
    return EvaluationDataset::factory()->for(test()->team)->create();
}

function runEval(EvaluationDataset $dataset, Agent $agent, bool $required = false)
{
    return app(EvaluationRunner::class)->run($dataset, $agent, test()->owner, Environment::Production, $required);
}

it('passes a no-tool case meeting its correctness and safety assertions', function () {
    bindFakeRouter()->textThen('Twelve vessels are on schedule today.');
    $agent = evalAgent();
    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->expects(['vessels'])->forbids(['password'])->create();

    $evaluation = runEval($dataset, $agent, required: true);

    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->cases_total)->toBe(1)
        ->and($evaluation->cases_passed)->toBe(1)
        ->and($evaluation->pass_rate)->toBe(100.0)
        ->and($evaluation->correctness_rate)->toBe(100.0)
        ->and($evaluation->safety_rate)->toBe(100.0)
        ->and($evaluation->is_required)->toBeTrue()
        ->and($evaluation->agent_version)->toBe($agent->version)
        ->and($evaluation->model_code)->toBe($this->model->code)
        ->and($evaluation->results)->toHaveCount(1)
        ->and($evaluation->results->first()->passed)->toBeTrue();
});

it('services a client-side tool case from its stub', function () {
    bindFakeRouter()->toolCallThen('getRecords', ['query' => 'today'])->textThen('Found the records.');
    $agent = evalAgent();
    $tool = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'getRecords',
        'execution_mode' => ExecMode::Client,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['records' => 'array', 'total' => 'number'],
    ]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')
        ->clientTool('getRecords', ['records' => ['a'], 'total' => 1])
        ->expects(['records'])
        ->create();

    $evaluation = runEval($dataset, $agent);

    $result = $evaluation->results->first();
    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($result->passed)->toBeTrue()
        ->and(collect($result->checks)->firstWhere('type', 'tool')['passed'])->toBeTrue();
});

it('passes a RAG case that surfaces a citation', function () {
    $source = KnowledgeSource::factory()->for($this->team)->create([
        'application_id' => null,
        'status' => KnowledgeSourceStatus::Active,
        'environments' => [Environment::Production->value],
    ]);
    app(KnowledgeIndexer::class)->ingestDocument($source, [
        'title' => 'Berth Policy',
        'uri' => 'https://policy.example/berth',
        'body' => 'Berth allocation prioritizes vessels by arrival window.',
    ]);
    $tool = ToolContract::factory()->for($this->team)->create([
        'application_id' => null,
        'slug' => 'searchPolicy',
        'scope' => ToolScope::Global,
        'execution_mode' => ExecMode::Knowledge,
        'knowledge_source_id' => $source->id,
        'knowledge_config' => ['top_k' => 5, 'min_score' => 0.1],
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['matches' => 'array', 'citations' => 'array'],
    ]);
    $agent = evalAgent();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('searchPolicy', ['query' => 'berth allocation'])->textThen('Policy answer.');

    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->rag('searchPolicy')->create();

    $evaluation = runEval($dataset, $agent);

    $result = $evaluation->results->first();
    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->citation_rate)->toBe(100.0)
        ->and($result->citations)->not->toBeEmpty()
        ->and(collect($result->checks)->firstWhere('type', 'citation')['passed'])->toBeTrue();
});

it('fails a client-tool case with no stub (the run never resolves)', function () {
    bindFakeRouter()->toolCallThen('getRecords', ['query' => 'today']);
    $agent = evalAgent();
    $tool = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'getRecords',
        'execution_mode' => ExecMode::Client,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['records' => 'array'],
    ]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->state(['kind' => EvaluationCaseKind::ClientTool])->create();

    $evaluation = runEval($dataset, $agent);

    expect($evaluation->status)->toBe(EvaluationStatus::Failed)
        ->and($evaluation->results->first()->failure_reason)->toBe('run_not_completed');
});

it('fails a case whose stub violates the output schema', function () {
    bindFakeRouter()->toolCallThen('getRecords', ['query' => 'today'])->textThen('done');
    $agent = evalAgent();
    $tool = ToolContract::factory()->for($this->team)->for($this->application)->create([
        'slug' => 'getRecords',
        'execution_mode' => ExecMode::Client,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['records' => 'array', 'total' => 'number'],
    ]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')
        ->clientTool('getRecords', ['records' => 'not-an-array'])
        ->create();

    $evaluation = runEval($dataset, $agent);

    expect($evaluation->status)->toBe(EvaluationStatus::Failed);
});

it('fails the evaluation when a correctness assertion is not met', function () {
    bindFakeRouter()->textThen('Nothing relevant here.');
    $agent = evalAgent();
    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->expects(['vessels'])->create();

    $evaluation = runEval($dataset, $agent);

    expect($evaluation->status)->toBe(EvaluationStatus::Failed)
        ->and($evaluation->correctness_rate)->toBe(0.0)
        ->and($evaluation->results->first()->failure_reason)->toBe('correctness_failed');
});

it('enforces cost and latency ceilings', function () {
    bindFakeRouter()->textThen('ok', tokensIn: 2000, tokensOut: 2000);
    $agent = evalAgent();
    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->state([
        'expectations' => [
            'expected_contains' => [],
            'expected_tool' => null,
            'forbidden_phrases' => [],
            'expects_citation' => false,
            'max_cost' => 0.0,
            'max_latency_ms' => 0,
        ],
    ])->create();

    $evaluation = runEval($dataset, $agent);

    $checks = collect($evaluation->results->first()->checks);
    expect($evaluation->status)->toBe(EvaluationStatus::Failed)
        ->and($checks->firstWhere('type', 'cost')['passed'])->toBeFalse()
        ->and($checks->firstWhere('type', 'latency')['passed'])->toBeFalse();
});

it('evaluates a candidate agent that is not yet published', function () {
    bindFakeRouter()->textThen('Candidate response.');
    $agent = evalAgent(['status' => AgentStatus::Testing], published: false);
    $dataset = evalDataset();
    EvaluationCase::factory()->for($dataset, 'dataset')->expects(['Candidate'])->create();

    $evaluation = runEval($dataset, $agent);

    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->results->first()->run->status)->toBe(RunStatus::Completed);
});

it('passes an empty dataset vacuously', function () {
    $agent = evalAgent();
    $evaluation = runEval(evalDataset(), $agent);

    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->cases_total)->toBe(0)
        ->and($evaluation->pass_rate)->toBe(100.0)
        ->and($evaluation->correctness_rate)->toBe(100.0);
});
