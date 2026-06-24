<?php

use App\Enums\ExecMode;
use App\Enums\KnowledgeSourceStatus;
use App\Models\ApprovalRequest;
use App\Models\KnowledgeSource;
use App\Models\ToolContract;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use Inertia\Testing\AssertableInertia as Assert;

test('the knowledge console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('knowledge', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maac/knowledge'));
});

test('a platform admin registers an active internal knowledge source', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Company Policies',
            'description' => 'Approved policy docs.',
            'sensitivity' => 'internal',
            'environments' => ['production', 'staging'],
        ])
        ->assertRedirect();

    $source = KnowledgeSource::firstWhere('name', 'Company Policies');

    expect($source)->not->toBeNull()
        ->and($source->team_id)->toBe($team->id)
        ->and($source->status)->toBe(KnowledgeSourceStatus::Active)
        ->and($source->requires_approval)->toBeFalse()
        ->and($source->creator->is($owner))->toBeTrue()
        ->and(ApprovalRequest::where('subject_id', $source->id)->exists())->toBeFalse();
});

test('a confidential source is gated behind ingestion approval', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Restricted Contracts',
            'sensitivity' => 'confidential',
            'environments' => ['production'],
        ])
        ->assertRedirect();

    $source = KnowledgeSource::firstWhere('name', 'Restricted Contracts');

    expect($source->status)->toBe(KnowledgeSourceStatus::Draft)
        ->and($source->requires_approval)->toBeTrue()
        ->and(ApprovalRequest::where('subject_id', $source->id)->where('type', 'knowledge_ingestion')->exists())->toBeTrue();
});

test('an internal source flagged for approval is also gated', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Flagged Source',
            'sensitivity' => 'internal',
            'requires_approval' => true,
            'environments' => ['production'],
        ])
        ->assertRedirect();

    expect(KnowledgeSource::firstWhere('name', 'Flagged Source')->status)->toBe(KnowledgeSourceStatus::Draft);
});

test('source registration validates its required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.store', ['current_team' => $team->slug]), [
            'name' => '',
            'environments' => [],
        ])
        ->assertSessionHasErrors(['name', 'sensitivity', 'environments']);
});

test('a plain member cannot register a knowledge source', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('knowledge-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'sensitivity' => 'internal',
            'environments' => ['production'],
        ])
        ->assertForbidden();
});

test('a source can be updated and toggled', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('knowledge-sources.update', ['current_team' => $team->slug, 'knowledgeSource' => $source->slug]), [
            'name' => 'Renamed Source',
            'status' => 'disabled',
        ])
        ->assertRedirect();

    $fresh = $source->fresh();
    expect($fresh->name)->toBe('Renamed Source')
        ->and($fresh->status)->toBe(KnowledgeSourceStatus::Disabled);
});

test('a document is ingested and indexed into chunks', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.documents.store', ['current_team' => $team->slug, 'knowledgeSource' => $source->slug]), [
            'title' => 'Berth Policy',
            'uri' => 'https://policy.example/berth',
            'body' => "Berth allocation prioritizes vessels by arrival window.\n\nDelayed vessels are reassigned.",
            'metadata' => ['author' => 'Ops', 'published_at' => '2026-01-01'],
        ])
        ->assertRedirect();

    $fresh = $source->fresh();
    expect($fresh->document_count)->toBe(1)
        ->and($fresh->chunk_count)->toBe(2)
        ->and($fresh->documents()->first()->title)->toBe('Berth Policy');
});

test('removing a document re-indexes the source', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();
    app(KnowledgeIndexer::class)->ingestDocument($source, ['title' => 'Doc', 'body' => 'Some indexed content here.']);
    $document = $source->documents()->first();

    $this->actingAs($owner)
        ->delete(route('knowledge-documents.destroy', ['current_team' => $team->slug, 'knowledgeDocument' => $document->id]))
        ->assertRedirect();

    expect($source->fresh()->document_count)->toBe(0)
        ->and($source->fresh()->chunk_count)->toBe(0);
});

test('a source can be re-indexed and deleted', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('knowledge-sources.reindex', ['current_team' => $team->slug, 'knowledgeSource' => $source->slug]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->delete(route('knowledge-sources.destroy', ['current_team' => $team->slug, 'knowledgeSource' => $source->slug]))
        ->assertRedirect();

    expect($source->fresh()->trashed())->toBeTrue();
});

test('the shared maac prop exposes knowledge sources with their documents', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create(['name' => 'Ops Manual']);
    app(KnowledgeIndexer::class)->ingestDocument($source, ['title' => 'Doc A', 'body' => 'content']);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maac.knowledgeSources', 1)
            ->where('maac.knowledgeSources.0.name', 'Ops Manual')
            ->where('maac.knowledgeSources.0.documentCount', 1)
            ->where('maac.knowledgeSources.0.documents.0.title', 'Doc A'));
});

test('a knowledge-mode tool contract maps to a source with a retrieval policy', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'searchPolicies',
            'scope' => 'global',
            'execution_mode' => 'knowledge',
            'sensitivity' => 'internal',
            'timeout_seconds' => 8,
            'max_payload_kb' => 512,
            'input_schema' => ['query' => 'string'],
            'output_schema' => ['matches' => 'array', 'citations' => 'array'],
            'knowledge_source_id' => $source->id,
            'knowledge_config' => ['top_k' => 3, 'min_score' => 0.2],
        ])
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'searchPolicies');

    expect($tool->execution_mode)->toBe(ExecMode::Knowledge)
        ->and($tool->knowledge_source_id)->toBe($source->id)
        ->and($tool->knowledgeConfig())->toBe(['top_k' => 3, 'min_score' => 0.2])
        ->and($tool->status)->toBe('Active');
});

test('a knowledge tool with no retrieval policy falls back to defaults', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'searchDefaults',
            'scope' => 'global',
            'execution_mode' => 'knowledge',
            'sensitivity' => 'internal',
            'timeout_seconds' => 8,
            'max_payload_kb' => 512,
            'input_schema' => ['query' => 'string'],
            'output_schema' => ['matches' => 'array', 'citations' => 'array'],
            'knowledge_source_id' => $source->id,
        ])
        ->assertRedirect();

    expect(ToolContract::firstWhere('name', 'searchDefaults')->knowledgeConfig())
        ->toBe(['top_k' => 5, 'min_score' => 0.1]);
});

test('a sensitive knowledge tool requiring approval starts as a draft', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'searchConfidential',
            'scope' => 'global',
            'execution_mode' => 'knowledge',
            'sensitivity' => 'confidential',
            'requires_approval' => true,
            'timeout_seconds' => 8,
            'max_payload_kb' => 512,
            'input_schema' => ['query' => 'string'],
            'output_schema' => ['matches' => 'array', 'citations' => 'array'],
            'knowledge_source_id' => $source->id,
        ])
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'searchConfidential');

    expect($tool->status)->toBe('Draft')
        ->and(ApprovalRequest::where('subject_id', $tool->id)->where('type', 'tool_contract')->exists())->toBeTrue();
});

test('switching a knowledge tool to another mode clears its source mapping', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->create();
    $tool = ToolContract::factory()->for($team)->create([
        'execution_mode' => ExecMode::Knowledge,
        'knowledge_source_id' => $source->id,
        'knowledge_config' => ['top_k' => 5, 'min_score' => 0.1],
    ]);

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $team->slug, 'tool' => $tool->slug]), [
            'execution_mode' => 'hosted',
        ])
        ->assertRedirect();

    $fresh = $tool->fresh();
    expect($fresh->knowledge_source_id)->toBeNull()
        ->and($fresh->knowledge_config)->toBeNull();
});
