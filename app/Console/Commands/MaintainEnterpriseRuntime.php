<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverAuditArchive;
use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessKnowledgeDocument;
use App\Models\ApprovalRequest;
use App\Models\AuditArchiveOutbox;
use App\Models\KnowledgeDocument;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('maacc:maintain-runtime')]
#[Description('Expire stale governance state, recover outboxes and ingestion, and prune retained transient records')]
class MaintainEnterpriseRuntime extends Command
{
    public function handle(): int
    {
        $expiredApprovals = ApprovalRequest::query()
            ->where('status', ApprovalStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => ApprovalStatus::Expired,
                'pending_key' => null,
                'decision_note' => 'Expired automatically by the enterprise runtime maintainer.',
                'decided_at' => now(),
            ]);

        $staleBefore = now()->subSeconds((int) config('maacc.runtime.webhooks.stale_claim_seconds', 180));
        $staleWebhooks = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending)
            ->whereNotNull('processing_token')
            ->where('processing_claimed_at', '<=', $staleBefore)
            ->get();

        foreach ($staleWebhooks as $delivery) {
            $delivery->update(['processing_token' => null, 'processing_claimed_at' => null]);
            DeliverWebhook::dispatch($delivery)->onQueue('webhooks');
        }

        $auditOutboxes = AuditArchiveOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->get();

        foreach ($auditOutboxes as $outbox) {
            $outbox->update(['status' => 'pending', 'available_at' => now()]);
            DeliverAuditArchive::dispatch($outbox)->onQueue('audit');
        }

        $documents = KnowledgeDocument::query()
            ->whereIn('ingestion_status', [KnowledgeDocumentStatus::Pending, KnowledgeDocumentStatus::Scanning, KnowledgeDocumentStatus::Failed])
            ->where('updated_at', '<=', now()->subMinute())
            ->get();

        foreach ($documents as $document) {
            if ($document->ingestion_status === KnowledgeDocumentStatus::Scanning) {
                $document->update(['ingestion_status' => KnowledgeDocumentStatus::Failed]);
            }

            ProcessKnowledgeDocument::dispatch($document)->onQueue('ingestion');
        }

        $prunedWebhooks = WebhookDelivery::query()
            ->whereIn('status', [WebhookDeliveryStatus::Delivered, WebhookDeliveryStatus::Failed])
            ->whereNotNull('retained_until')
            ->where('retained_until', '<=', now())
            ->delete();

        WebhookEndpoint::query()
            ->whereNotNull('previous_secret_expires_at')
            ->where('previous_secret_expires_at', '<=', now())
            ->update(['previous_secret' => null, 'previous_secret_expires_at' => null]);

        $quarantineCutoff = now()->subDays((int) config('maacc.governance.quarantine_retention_days', 7));
        $quarantined = KnowledgeDocument::query()
            ->where('ingestion_status', KnowledgeDocumentStatus::Quarantined)
            ->where('processed_at', '<=', $quarantineCutoff)
            ->get();

        foreach ($quarantined as $document) {
            if ($document->disk !== null && $document->storage_path !== null) {
                Storage::disk($document->disk)->delete($document->storage_path);
            }

            $document->delete();
        }

        $expiredTokens = 0;

        foreach (['oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_auth_codes', 'oauth_device_codes'] as $table) {
            $expiredTokens += DB::table($table)->where('expires_at', '<=', now())->delete();
        }

        $this->info("Expired {$expiredApprovals} approval(s); recovered {$staleWebhooks->count()} webhook(s), {$auditOutboxes->count()} archive item(s), and {$documents->count()} upload(s); pruned {$prunedWebhooks} webhook(s), {$quarantined->count()} quarantined upload(s), and {$expiredTokens} OAuth token(s).");

        return self::SUCCESS;
    }
}
