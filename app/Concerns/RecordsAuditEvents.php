<?php

namespace App\Concerns;

use App\Models\AuditEvent;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Records MAACC audit events from model lifecycle events (created/updated/
 * deleted), keeping audit logging on the model rather than in controllers.
 * Each consuming model resolves the owning team via {@see self::auditTeam()}.
 */
trait RecordsAuditEvents
{
    /**
     * Boot the trait and hook the audited model lifecycle events.
     */
    public static function bootRecordsAuditEvents(): void
    {
        static::created(fn (self $model) => $model->recordAuditEvent('created'));
        static::updated(fn (self $model) => $model->recordAuditEvent('updated'));
        static::deleted(fn (self $model) => $model->recordAuditEvent('deleted'));
    }

    /**
     * Record an audit event for the given lifecycle event.
     */
    public function recordAuditEvent(string $event): void
    {
        $team = $this->auditTeam();

        if ($team !== null) {
            $user = auth()->user();

            AuditEvent::create([
                'team_id' => $team->id,
                'actor_user_id' => $user?->getAuthIdentifier(),
                'actor_label' => $user?->name,
                'action' => Str::snake(class_basename($this)).'.'.$event,
                'auditable_type' => $this::class,
                'auditable_id' => (string) $this->getKey(),
                'metadata' => $event === 'updated'
                    ? (Arr::except($this->getChanges(), ['updated_at']) ?: null)
                    : null,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Resolve the team the audit event should be recorded under.
     */
    abstract protected function auditTeam(): ?Team;
}
