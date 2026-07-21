<?php

namespace App\Support\Sdk;

use App\Enums\Environment;
use App\Enums\ImplStatus;
use App\Models\ToolContract;
use App\Models\ToolContractVersion;
use App\Models\ToolImplementation;
use App\Support\Governance\AuditLedger;

/**
 * Records audit-log entries for the version journey's governance-relevant
 * transitions — a new contract version being minted, and a client handler
 * drifting out of or recovering back into compatibility — so the journey is
 * visible in the team's existing audit trail and signed audit export alongside
 * the dedicated histories.
 */
class JourneyAuditRecorder
{
    /**
     * Record that a contract minted a new version (from → to).
     */
    public function contractVersioned(ToolContract $contract, string $fromVersion, ToolContractVersion $version): void
    {
        $this->record($contract->team_id, 'tool_contract.versioned', ToolContract::class, (string) $contract->getKey(), [
            'tool' => $contract->slug,
            'from' => $fromVersion,
            'to' => $version->version,
            'sequence' => $version->sequence,
            'schema_fingerprint' => $version->schema_fingerprint,
        ]);
    }

    /**
     * Record a client handler transition (drift or recovery) under the action
     * matching the webhook event it fired.
     */
    public function implementationTransition(ToolContract $contract, ToolImplementation $implementation, ?ImplStatus $previousStatus, string $action): void
    {
        $this->record($contract->team_id, $action, ToolImplementation::class, (string) $implementation->getKey(), [
            'tool' => $contract->slug,
            'application_id' => $implementation->application_id,
            'environment' => $implementation->environment->value,
            'from' => $previousStatus?->value,
            'to' => $implementation->status->value,
            'reported_version' => $implementation->implemented_version,
            'contract_version' => $contract->version,
        ], $implementation->environment);
    }

    /**
     * Persist an audit event under the resolving user (null for SDK/system).
     *
     * @param  array<string, mixed>  $metadata
     */
    private function record(int $teamId, string $action, string $auditableType, string $auditableId, array $metadata, ?Environment $environment = null): void
    {
        $user = auth()->user();

        app(AuditLedger::class)->record([
            'team_id' => $teamId,
            'actor_user_id' => $user?->getAuthIdentifier(),
            'actor_label' => $user?->name,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'environment' => $environment?->value,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
