<?php

namespace App\Support\Governance;

use App\Jobs\DeliverAuditArchive;
use App\Models\AuditArchiveOutbox;
use App\Models\AuditChainHead;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomic append-only audit writer. Sequence allocation, chain-head advance,
 * signed event persistence, and archive-outbox insertion commit together.
 */
class AuditLedger
{
    public function __construct(private readonly AuditSigner $signer) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): AuditEvent
    {
        [$event, $outbox] = DB::transaction(function () use ($attributes): array {
            DB::table('audit_chain_heads')->insertOrIgnore([
                'team_id' => $attributes['team_id'],
                'next_sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $head = AuditChainHead::query()
                ->where('team_id', $attributes['team_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $eventAttributes = [
                ...$attributes,
                'id' => (string) Str::uuid(),
                'sequence' => $head->next_sequence,
                'previous_signature' => $head->last_signature,
                'signature_key_id' => $this->signer->keyId('chain'),
            ];

            $event = AuditEvent::withoutEvents(function () use ($eventAttributes): AuditEvent {
                $event = new AuditEvent;
                $event->forceFill($eventAttributes);
                $event->save();

                // Sign the exact persisted representation after Eloquent casts
                // have normalized enums, JSON, identifiers, and null values.
                $event->forceFill(['signature' => $this->signer->signEvent($event)]);
                $event->saveQuietly();

                return $event;
            });
            $payload = $this->signer->eventPayload($event);
            $outbox = AuditArchiveOutbox::query()->create([
                'team_id' => $event->team_id,
                'audit_event_id' => $event->id,
                'payload' => $payload,
                'signature' => $event->signature,
                'signature_key_id' => $event->signature_key_id,
                'status' => 'pending',
                'available_at' => now(),
            ]);

            $head->update([
                'next_sequence' => $event->sequence + 1,
                'last_signature' => $event->signature,
            ]);

            return [$event, $outbox];
        });

        DeliverAuditArchive::dispatch($outbox)->onQueue('audit')->afterCommit();

        return $event;
    }
}
