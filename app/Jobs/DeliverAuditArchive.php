<?php

namespace App\Jobs;

use App\Models\AuditArchiveOutbox;
use App\Models\AuditEvent;
use App\Support\Governance\AuditSigner;
use App\Support\Governance\Contracts\AuditArchive;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeliverAuditArchive implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = true;

    public function __construct(public AuditArchiveOutbox $outbox) {}

    public function handle(AuditArchive $archive, AuditSigner $signer): void
    {
        $outbox = AuditArchiveOutbox::query()->find($this->outbox->id);

        if (! $outbox instanceof AuditArchiveOutbox || $outbox->status === 'delivered') {
            return;
        }

        $event = AuditEvent::query()->find($outbox->audit_event_id);

        if (! $event instanceof AuditEvent || ! $signer->verifyEvent($event) || ! hash_equals($event->signature ?? '', $outbox->signature)) {
            throw new RuntimeException('Audit archive delivery refused an invalid or missing source event.');
        }

        $receipt = $archive->archive($outbox);

        DB::transaction(function () use ($outbox, $event, $receipt): void {
            $outbox->update([
                'status' => 'delivered',
                'attempts' => $outbox->attempts + 1,
                'delivered_at' => now(),
                'error' => null,
            ]);
            $event->update([
                'archived_at' => now(),
                'archive_receipt' => $receipt,
            ]);
        });
    }

    public function uniqueId(): string
    {
        return 'audit-archive:'.$this->outbox->audit_event_id;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function failed(?Throwable $exception): void
    {
        AuditArchiveOutbox::query()->whereKey($this->outbox->id)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'error' => 'Audit archival failed and requires recovery.',
            'available_at' => now()->addMinutes(5),
        ]);
    }
}
