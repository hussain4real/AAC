<?php

namespace App\Concerns;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records MAAC audit events for administrative changes from controllers.
 */
trait RecordsMaacAudit
{
    /**
     * Record an audit event for the current actor against the given record.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(Request $request, string $action, Model $auditable, array $metadata = []): void
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if ($team === null) {
            return;
        }

        AuditEvent::create([
            'team_id' => $team->id,
            'actor_user_id' => $user->id,
            'actor_label' => $user->name,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => (string) $auditable->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
