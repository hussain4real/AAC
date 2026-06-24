<?php

use App\Enums\AgentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;

/**
 * End-to-end proof of the Phase 6F surface, driven entirely through the
 * authenticated console write endpoints: index a knowledge source, assign a RAG
 * tool to an agent, run a governed evaluation through the real runtime, inspect
 * the citation a case surfaced, and confirm the activity is auditable.
 */
beforeEach(function () {
    [$this->owner, $this->team] = ownerAndTeam();
    $this->slug = $this->team->slug;
});

function e2ePost(string $name, array $params, array $payload)
{
    return test()->actingAs(test()->owner)
        ->post(route($name, [...['current_team' => test()->slug], ...$params]), $payload)
        ->assertRedirect();
}

it('indexes a source, runs a RAG agent through an evaluation, and audits it', function () {
    // 1. Application, model, and project (console setup path).
    e2ePost('applications.store', [], [
        'name' => 'Cargo Insights',
        'code' => 'CARGO',
        'department' => 'Logistics',
        'owner_name' => 'Owner',
        'owner_email' => 'owner@milaha.com',
        'environment' => 'production',
    ]);
    $application = Application::firstWhere('code', 'CARGO');

    e2ePost('llm-providers.store', [], [
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

    e2ePost('projects.store', [], [
        'application_id' => $application->id,
        'name' => 'Cargo Project',
        'environment' => 'production',
    ]);
    $project = Project::firstWhere('application_id', $application->id);

    // 2. Register a knowledge source and ingest a document (indexed into chunks).
    e2ePost('knowledge-sources.store', [], [
        'name' => 'Policy Library',
        'sensitivity' => 'internal',
        'environments' => ['production'],
    ]);
    $source = KnowledgeSource::firstWhere('name', 'Policy Library');

    e2ePost('knowledge-sources.documents.store', ['knowledgeSource' => $source->slug], [
        'title' => 'Berth Allocation Policy',
        'uri' => 'https://policy.example/berth',
        'body' => 'Berth allocation prioritizes vessels by arrival window. Delayed vessels are reassigned to the next berth.',
    ]);
    expect($source->fresh()->chunk_count)->toBeGreaterThan(0);

    // 3. Create a knowledge (RAG) tool mapped to the source.
    e2ePost('tools.store', [], [
        'name' => 'searchPolicies',
        'scope' => 'global',
        'execution_mode' => 'knowledge',
        'sensitivity' => 'internal',
        'timeout_seconds' => 8,
        'max_payload_kb' => 512,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['matches' => 'array', 'citations' => 'array'],
        'knowledge_source_id' => $source->id,
        'knowledge_config' => ['top_k' => 5, 'min_score' => 0.1],
    ]);
    $tool = ToolContract::firstWhere('name', 'searchPolicies');

    // 4. Create an agent that uses the RAG tool.
    e2ePost('agents.store', [], [
        'project_id' => $project->id,
        'llm_provider_id' => $provider->id,
        'name' => 'Policy QA Agent',
        'agent_slug' => 'policy-qa',
        'system_prompt' => 'Answer using the policy library and cite sources.',
        'temperature' => 0.2,
        'max_tokens' => 1200,
        'tool_ids' => [$tool->id],
    ]);
    $agent = Agent::firstWhere('agent_slug', 'policy-qa');

    // 5. Build a golden dataset with a RAG case that requires a citation.
    e2ePost('evaluation-datasets.store', [], [
        'name' => 'Policy QA release gate',
        'project_id' => $project->id,
    ]);
    $dataset = EvaluationDataset::firstWhere('name', 'Policy QA release gate');

    e2ePost('evaluation-cases.store', [], [
        'evaluation_dataset_id' => $dataset->id,
        'name' => 'Cites the berth policy',
        'kind' => 'rag',
        'input' => 'What is the berth allocation policy?',
        'expectations' => [
            'expected_tool' => $tool->slug,
            'expects_citation' => true,
        ],
    ]);

    // 6. Run the evaluation as a promotion gate. The scripted router calls the
    // RAG tool with a real query so retrieval surfaces a real citation.
    bindFakeRouter()
        ->toolCallThen($tool->slug, ['query' => 'berth allocation'])
        ->textThen('Delayed vessels are reassigned to the next berth.');

    e2ePost('evaluations.store', [], [
        'evaluation_dataset_id' => $dataset->id,
        'agent_id' => $agent->id,
        'environment' => 'production',
        'is_required' => true,
    ]);

    $evaluation = Evaluation::firstWhere('agent_id', $agent->id);

    // The evaluation passed, surfaced a citation, and is a promotion gate.
    expect($evaluation->status)->toBe(EvaluationStatus::Passed)
        ->and($evaluation->is_required)->toBeTrue()
        ->and($evaluation->citation_rate)->toBe(100.0)
        ->and($evaluation->results->first()->citations[0]['document'])->toBe('Berth Allocation Policy');

    // The evaluation produced a real, audited agent run.
    $run = AgentRun::firstWhere('evaluation_id', $evaluation->id);
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->toolCalls()->where('tool_name', $tool->slug)->exists())->toBeTrue();

    // Activity is auditable.
    expect(AuditEvent::where('team_id', $this->team->id)->where('action', 'knowledge_source.created')->exists())->toBeTrue()
        ->and(AuditEvent::where('team_id', $this->team->id)->where('action', 'evaluation.created')->exists())->toBeTrue();

    // The promotion gate now permits publication; publishing succeeds.
    e2ePost('agents.publish', ['agent' => $agent->slug], []);
    expect($agent->fresh()->status)->toBe(AgentStatus::Published);
});
