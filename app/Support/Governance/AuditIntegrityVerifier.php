<?php

namespace App\Support\Governance;

use App\Models\AuditArchiveOutbox;
use App\Models\AuditChainHead;
use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\Team;
use App\Support\Governance\Contracts\AuditArchive;

/** Independently verifies signatures, ordering, completeness, and archives. */
class AuditIntegrityVerifier
{
    public function __construct(
        private readonly AuditSigner $signer,
        private readonly AuditArchive $archive,
    ) {}

    /** @return array{valid: bool, errors: list<array{code: string, sequence?: int, event_id?: string}>, verified: int, expected: int} */
    public function verify(Team $team): array
    {
        $head = AuditChainHead::query()->find($team->id);

        if (! $head instanceof AuditChainHead) {
            return ['valid' => false, 'errors' => [['code' => 'chain_head_missing']], 'verified' => 0, 'expected' => 0];
        }

        $events = [];

        foreach (AuditEvent::query()->where('team_id', $team->id)->get() as $event) {
            $events[(int) $event->sequence] = $event;
        }

        $outboxes = [];

        foreach (AuditArchiveOutbox::query()->where('team_id', $team->id)->get() as $outbox) {
            $outboxes[(int) ($outbox->payload['sequence'] ?? 0)] = $outbox;
        }
        $errors = [];
        $previous = null;
        $verified = 0;
        $expected = max(0, $head->next_sequence - 1);

        for ($sequence = 1; $sequence <= $expected; $sequence++) {
            $event = $events[$sequence] ?? null;
            $outbox = $outboxes[$sequence] ?? null;

            if (! $event instanceof AuditEvent && ! $outbox instanceof AuditArchiveOutbox) {
                $errors[] = ['code' => 'event_deleted_or_missing', 'sequence' => $sequence];

                continue;
            }

            if (! $event instanceof AuditEvent) {
                $retention = GovernanceSetting::forTeam($team)->retentionDaysFor('audit');

                if ($outbox->created_at?->gte(now()->subDays($retention))) {
                    $errors[] = ['code' => 'event_deleted_before_retention', 'sequence' => $sequence, 'event_id' => $outbox->audit_event_id];
                }
            }

            $payload = $event instanceof AuditEvent ? $this->signer->eventPayload($event) : $outbox->payload;
            $signature = $event instanceof AuditEvent ? (string) $event->signature : $outbox->signature;
            $keyId = $event instanceof AuditEvent ? (string) $event->signature_key_id : $outbox->signature_key_id;
            $eventId = (string) ($payload['id'] ?? '');

            if ((int) ($payload['sequence'] ?? 0) !== $sequence) {
                $errors[] = ['code' => 'event_reordered', 'sequence' => $sequence, 'event_id' => $eventId];
            }

            if (($payload['previous_signature'] ?? null) !== $previous) {
                $errors[] = ['code' => 'chain_link_invalid', 'sequence' => $sequence, 'event_id' => $eventId];
            }

            if (! $this->signer->verifyPayload($payload, $signature, $keyId)) {
                $errors[] = ['code' => 'signature_or_key_invalid', 'sequence' => $sequence, 'event_id' => $eventId];
            }

            if ($outbox instanceof AuditArchiveOutbox) {
                if (! hash_equals($signature, $outbox->signature) || $outbox->payload !== $payload) {
                    $errors[] = ['code' => 'archive_payload_mismatch', 'sequence' => $sequence, 'event_id' => $eventId];
                }

                if ($outbox->status === 'failed') {
                    $errors[] = ['code' => 'archive_delivery_failed', 'sequence' => $sequence, 'event_id' => $eventId];
                } elseif ($outbox->status === 'delivered' && ! $this->archive->exists($outbox)) {
                    $errors[] = ['code' => 'archive_object_missing', 'sequence' => $sequence, 'event_id' => $eventId];
                } elseif ($outbox->status !== 'delivered') {
                    $errors[] = ['code' => 'archive_delivery_pending', 'sequence' => $sequence, 'event_id' => $eventId];
                }
            } else {
                $errors[] = ['code' => 'archive_outbox_missing', 'sequence' => $sequence, 'event_id' => $eventId];
            }

            $previous = $signature;
            $verified++;
        }

        if ($head->last_signature !== $previous) {
            $errors[] = ['code' => 'chain_head_mismatch'];
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'verified' => $verified, 'expected' => $expected];
    }
}
