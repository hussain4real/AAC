<?php

use App\Enums\ApprovalStatus;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverAuditArchive;
use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessKnowledgeDocument;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\AuditArchiveOutbox;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Governance\AuditLedger;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('the runtime maintainer expires and recovers governed transient state', function () {
    Storage::fake('local');
    Queue::fake();
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $approval = ApprovalRequest::factory()->for($team)->create([
        'pending_key' => hash('sha256', 'expired'),
        'expires_at' => now()->subMinute(),
    ]);
    $endpoint = WebhookEndpoint::factory()->for($application)->create();
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'status' => WebhookDeliveryStatus::Pending,
        'processing_token' => fake()->uuid(),
        'processing_claimed_at' => now()->subMinutes(10),
    ]);
    app(AuditLedger::class)->record(['team_id' => $team->id, 'action' => 'maintenance.test']);
    $outbox = AuditArchiveOutbox::firstOrFail();
    $outbox->update(['status' => 'failed', 'available_at' => now()->subMinute()]);

    $source = KnowledgeSource::factory()->for($team)->create();
    $document = KnowledgeDocument::factory()->for($source, 'source')->uploaded('queued.txt', 'txt')->create([
        'ingestion_status' => KnowledgeDocumentStatus::Failed,
        'updated_at' => now()->subMinutes(5),
    ]);
    $scanning = KnowledgeDocument::factory()->for($source, 'source')->uploaded('scanning.txt', 'txt')->create([
        'ingestion_status' => KnowledgeDocumentStatus::Scanning,
        'updated_at' => now()->subMinutes(5),
    ]);
    Storage::disk('local')->put('knowledge-quarantine/expired.txt', 'expired');
    $quarantined = KnowledgeDocument::factory()->for($source, 'source')->create([
        'ingestion_status' => KnowledgeDocumentStatus::Quarantined,
        'disk' => 'local',
        'storage_path' => 'knowledge-quarantine/expired.txt',
        'processed_at' => now()->subDays(8),
    ]);

    $this->artisan('maacc:maintain-runtime')->assertSuccessful();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Expired)
        ->and($approval->fresh()->pending_key)->toBeNull()
        ->and($delivery->fresh()->processing_token)->toBeNull()
        ->and($outbox->fresh()->status)->toBe('pending')
        ->and($scanning->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Failed)
        ->and($quarantined->fresh())->toBeNull();

    Storage::disk('local')->assertMissing('knowledge-quarantine/expired.txt');

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->delivery->is($delivery));
    Queue::assertPushed(DeliverAuditArchive::class, fn (DeliverAuditArchive $job): bool => $job->outbox->is($outbox));
    Queue::assertPushed(ProcessKnowledgeDocument::class, fn (ProcessKnowledgeDocument $job): bool => $job->document->is($document));
    Queue::assertPushed(ProcessKnowledgeDocument::class, fn (ProcessKnowledgeDocument $job): bool => $job->document->is($scanning));
});
