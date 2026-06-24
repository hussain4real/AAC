<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\KnowledgeSourceStatus;
use App\Http\Resources\Maac\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\KnowledgeSource;
use App\Support\Governance\ApprovalManager;

test('requesting knowledge ingestion opens an idempotent pending approval', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->sensitive()->create();

    $first = app(ApprovalManager::class)->requestKnowledgeIngestion($source, $owner);
    $second = app(ApprovalManager::class)->requestKnowledgeIngestion($source, $owner);

    expect($first->is($second))->toBeTrue()
        ->and($first->type)->toBe(ApprovalType::KnowledgeIngestion)
        ->and($first->status)->toBe(ApprovalStatus::Pending)
        ->and($first->sensitivity->value)->toBe('confidential');
});

test('approving a knowledge ingestion request activates the source', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->sensitive()->create();
    $request = app(ApprovalManager::class)->requestKnowledgeIngestion($source, $owner);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($source->fresh()->status)->toBe(KnowledgeSourceStatus::Active);
});

test('the approval request resource builds a knowledge source detail view', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->sensitive()->create(['name' => 'Restricted Docs']);
    $request = app(ApprovalManager::class)->requestKnowledgeIngestion($source, $owner);

    $payload = (new ApprovalRequestResource($request->load('subject')))
        ->toArray(request());

    expect($payload['subject']['kind'])->toBe('Knowledge source')
        ->and(collect($payload['subject']['fields'])->firstWhere('k', 'Sensitivity')['v'])->toBe('Confidential');
});

test('a knowledge ingestion approval can be opened through the governance endpoint', function () {
    [$owner, $team] = ownerAndTeam();
    $source = KnowledgeSource::factory()->for($team)->draft()->create();

    $this->actingAs($owner)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => 'knowledge_ingestion',
            'subject' => $source->slug,
        ])
        ->assertRedirect();

    expect(ApprovalRequest::where('subject_id', $source->id)->where('type', 'knowledge_ingestion')->exists())->toBeTrue();
});
