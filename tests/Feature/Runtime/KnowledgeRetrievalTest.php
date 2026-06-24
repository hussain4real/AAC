<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Enums\TraceEventType;
use App\Models\Agent;
use App\Models\Application;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\Knowledge\Contracts\KnowledgeRetriever;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use App\Support\Runtime\Knowledge\KnowledgeToolExecutor;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->source = KnowledgeSource::factory()->for($this->team)->create([
        'application_id' => null,
        'status' => KnowledgeSourceStatus::Active,
        'environments' => [Environment::Production->value],
    ]);

    $indexer = app(KnowledgeIndexer::class);
    $indexer->ingestDocument($this->source, [
        'title' => 'Berth Allocation Policy',
        'uri' => 'https://policy.example/berth',
        'metadata' => ['author' => 'Ops'],
        'body' => "Berth allocation prioritizes vessels by arrival window.\n\nA delayed vessel is reassigned to the next available berth.",
    ]);
    $indexer->ingestDocument($this->source, [
        'title' => 'Crew Manual',
        'body' => 'Crew lists must be filed before departure.',
    ]);
});

function knowledgeTool(KnowledgeSource $source, array $overrides = []): ToolContract
{
    return ToolContract::factory()->for($source->team)->create(array_merge([
        'application_id' => null,
        'scope' => ToolScope::Global,
        'execution_mode' => ExecMode::Knowledge,
        'knowledge_source_id' => $source->id,
        'knowledge_config' => ['top_k' => 5, 'min_score' => 0.1],
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['matches' => 'array', 'citations' => 'array'],
    ], $overrides));
}

it('indexes documents into chunks and tracks freshness', function () {
    $this->source->refresh();

    expect($this->source->document_count)->toBe(2)
        ->and($this->source->chunk_count)->toBe(3) // 2 paragraphs + 1 paragraph
        ->and($this->source->last_indexed_at)->not->toBeNull()
        ->and($this->source->documents()->first()->indexed_at)->not->toBeNull();
});

it('retrieves the most relevant chunks with citations and freshness', function () {
    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source),
        Environment::Production,
        ['query' => 'berth allocation policy'],
    );

    expect($result['matches'])->not->toBeEmpty()
        ->and($result['citations'][0]['document'])->toBe('Berth Allocation Policy')
        ->and($result['citations'][0]['uri'])->toBe('https://policy.example/berth')
        ->and($result['citations'][0]['score'])->toBeGreaterThan(0)
        ->and($result['citations'][0]['indexed_at'])->not->toBeNull()
        ->and($result['matches'][0]['source'])->toBe('Berth Allocation Policy');
});

it('returns no matches when nothing is relevant', function () {
    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source),
        Environment::Production,
        ['query' => 'unrelated quantum astrophysics'],
    );

    expect($result['matches'])->toBe([])
        ->and($result['citations'])->toBe([]);
});

it('honors the top_k retrieval limit', function () {
    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source, ['knowledge_config' => ['top_k' => 1, 'min_score' => 0.0]]),
        Environment::Production,
        ['query' => 'berth vessel crew departure'],
    );

    expect($result['matches'])->toHaveCount(1);
});

it('resolves the query from the first string argument when no query field', function () {
    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source, ['input_schema' => ['q' => 'string']]),
        Environment::Production,
        ['q' => 'berth allocation'],
    );

    expect($result['matches'])->not->toBeEmpty();
});

it('fails when the tool is not mapped to a source', function () {
    app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source, ['knowledge_source_id' => null]),
        Environment::Production,
        ['query' => 'berth'],
    );
})->throws(ToolExecutionException::class, 'not mapped to a knowledge source');

it('fails when the source is disabled', function () {
    $this->source->update(['status' => KnowledgeSourceStatus::Disabled]);

    expect(fn () => app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source),
        Environment::Production,
        ['query' => 'berth'],
    ))->toThrow(ToolExecutionException::class, 'disabled or not available');
});

it('fails when the source is not available in the environment', function () {
    expect(fn () => app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source),
        Environment::Staging,
        ['query' => 'berth'],
    ))->toThrow(ToolExecutionException::class, 'disabled or not available');
});

it('fails on an empty query', function () {
    expect(fn () => app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source),
        Environment::Production,
        ['query' => '   '],
    ))->toThrow(ToolExecutionException::class, 'requires a non-empty query');
});

it('drives a full RAG run through the runtime with citations and a trace', function () {
    $tool = knowledgeTool($this->source, ['slug' => 'searchPolicy']);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create([
        'environments' => [Environment::Production->value],
        'input_cost' => 1.0,
        'output_cost' => 2.0,
    ]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()
        ->toolCallThen('searchPolicy', ['query' => 'berth allocation'])
        ->textThen('Per policy, delayed vessels are reassigned to the next berth.');

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'What is the berth policy?', 'tester');

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toContain('reassigned');

    $call = $run->toolCalls()->where('tool_name', 'searchPolicy')->first();
    expect($call->result['citations'][0]['document'])->toBe('Berth Allocation Policy')
        ->and($run->traceEvents()->where('type', TraceEventType::ToolResultReceived)->exists())->toBeTrue();
});

it('fails the run when a knowledge tool requires approval but is not active', function () {
    $tool = knowledgeTool($this->source, [
        'slug' => 'gatedKnowledge',
        'requires_approval' => true,
        'status' => 'Draft',
        'sensitivity' => Sensitivity::Confidential,
    ]);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create(['environments' => [Environment::Production->value]]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('gatedKnowledge', ['query' => 'berth']);

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'q', 'tester');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('tool_requires_approval');
});

it('returns no matches for a stopword-only query or a zero limit', function () {
    $retriever = app(KnowledgeRetriever::class);

    expect($retriever->retrieve($this->source, 'the and of an', 5, 0.1))->toBe([])
        ->and($retriever->retrieve($this->source, 'berth', 0, 0.1))->toBe([]);
});

it('filters out chunks below the minimum relevance score', function () {
    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source, ['knowledge_config' => ['top_k' => 5, 'min_score' => 0.95]]),
        Environment::Production,
        ['query' => 'berth nonexistentterm'],
    );

    expect($result['matches'])->toBe([]);
});

it('fails when no argument yields a usable query', function () {
    expect(fn () => app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($this->source, ['input_schema' => ['count' => 'number?']]),
        Environment::Production,
        ['count' => 5],
    ))->toThrow(ToolExecutionException::class, 'requires a non-empty query');
});

it('produces no chunks for a whitespace-only document body', function () {
    $source = KnowledgeSource::factory()->for($this->team)->create(['application_id' => null]);

    $document = app(KnowledgeIndexer::class)->ingestDocument($source, ['title' => 'Blank', 'body' => '   ']);

    expect($source->fresh()->chunk_count)->toBe(0)
        ->and($document->indexed_at)->not->toBeNull();
});

it('fails the run when the knowledge source is unavailable', function () {
    $this->source->update(['status' => KnowledgeSourceStatus::Disabled]);
    $tool = knowledgeTool($this->source, ['slug' => 'searchDisabled']);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create(['environments' => [Environment::Production->value]]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('searchDisabled', ['query' => 'berth']);

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'q', 'tester');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('knowledge_unavailable');
});

it('reindexes a source and rebuilds its chunks', function () {
    $document = $this->source->documents()->first();
    $document->chunks()->delete();

    app(KnowledgeIndexer::class)->reindex($this->source->fresh());

    expect($this->source->fresh()->chunk_count)->toBe(3);
});

it('splits a long paragraph into word windows', function () {
    config(['maac.runtime.knowledge.chunk_size' => 5]);
    $source = KnowledgeSource::factory()->for($this->team)->create(['application_id' => null]);

    app(KnowledgeIndexer::class)->ingestDocument($source, [
        'title' => 'Long',
        'body' => 'one two three four five six seven eight nine ten eleven twelve',
    ]);

    expect($source->fresh()->chunk_count)->toBe(3); // ceil(12 / 5)
});

it('retrieves and cites chunks from an uploaded document', function () {
    Storage::fake('local');
    $source = KnowledgeSource::factory()->for($this->team)->create([
        'application_id' => null,
        'status' => KnowledgeSourceStatus::Active,
        'environments' => [Environment::Production->value],
    ]);

    Storage::disk('local')->put(
        'knowledge/uploaded.txt',
        "Tug boat scheduling assigns tugs by vessel draft.\n\nDeep-draft vessels get priority tug support.",
    );

    app(KnowledgeIndexer::class)->ingestStoredDocument($source, [
        'title' => 'Tug Scheduling Guide',
        'uri' => 'https://docs.example/tug-scheduling',
        'disk' => 'local',
        'storage_path' => 'knowledge/uploaded.txt',
        'original_filename' => 'tug-scheduling.txt',
        'mime_type' => 'text/plain',
        'file_size' => 120,
    ]);

    expect($source->fresh()->chunk_count)->toBe(2);

    $result = app(KnowledgeToolExecutor::class)->execute(
        knowledgeTool($source),
        Environment::Production,
        ['query' => 'tug scheduling vessel draft'],
    );

    expect($result['matches'])->not->toBeEmpty()
        ->and($result['citations'][0]['document'])->toBe('Tug Scheduling Guide')
        ->and($result['citations'][0]['uri'])->toBe('https://docs.example/tug-scheduling')
        ->and($result['citations'][0]['score'])->toBeGreaterThan(0)
        ->and($result['citations'][0]['indexed_at'])->not->toBeNull();
});
