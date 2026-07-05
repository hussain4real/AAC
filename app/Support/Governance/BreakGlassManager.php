<?php

namespace App\Support\Governance;

use App\Actions\Maacc\RevokeCredential;
use App\Enums\Environment;
use App\Enums\IncidentActionType;
use App\Enums\LlmStatus;
use App\Enums\McpConnectorStatus;
use App\Enums\WebhookEndpointStatus;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Credential;
use App\Models\IncidentAction;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Performs break-glass / incident-response controls: each method applies the
 * containment immediately (revoke a credential, disable a model, shut down a
 * connector, suspend a webhook, freeze or unfreeze an application's runtime),
 * records an {@see IncidentAction} on the timeline with the mandatory reason, and
 * writes a high-severity audit event. These deliberately bypass normal approval.
 */
class BreakGlassManager
{
    public function __construct(private readonly RevokeCredential $credentialRevoker) {}

    /**
     * Immediately revoke a credential (and its backing OAuth client/tokens).
     */
    public function revokeCredential(Credential $credential, User $actor, string $reason): IncidentAction
    {
        $credential->loadMissing('application.team');
        $this->credentialRevoker->handle($credential);

        return $this->record($credential->application->team, $actor, IncidentActionType::RevokeCredential, $credential, $credential->label, $reason, $credential->environment);
    }

    /**
     * Immediately disable a model so no run may use it in any environment.
     */
    public function disableModel(LlmProvider $model, User $actor, string $reason): IncidentAction
    {
        $model->update(['status' => LlmStatus::Blocked]);

        return $this->record($model->team, $actor, IncidentActionType::DisableModel, $model, $model->name, $reason, null);
    }

    /**
     * Immediately shut down an MCP connector so its tools can no longer execute.
     */
    public function shutdownConnector(McpConnector $connector, User $actor, string $reason): IncidentAction
    {
        $connector->update(['status' => McpConnectorStatus::Disabled]);

        return $this->record($connector->team, $actor, IncidentActionType::ShutdownConnector, $connector, $connector->name, $reason, null);
    }

    /**
     * Immediately suspend a webhook endpoint so it receives no further deliveries.
     */
    public function suspendWebhook(WebhookEndpoint $endpoint, User $actor, string $reason): IncidentAction
    {
        $endpoint->loadMissing('application.team');
        $endpoint->update(['status' => WebhookEndpointStatus::Disabled]);

        return $this->record($endpoint->application->team, $actor, IncidentActionType::SuspendWebhook, $endpoint, $endpoint->url, $reason, $endpoint->environment);
    }

    /**
     * Freeze an application's runtime: new runs are rejected and in-flight runs
     * halt until the freeze is lifted.
     */
    public function freezeApplication(Application $application, User $actor, string $reason): IncidentAction
    {
        $application->loadMissing('team');
        $application->update(['runtime_frozen_at' => Date::now(), 'runtime_frozen_by' => $actor->getAuthIdentifier()]);

        return $this->record($application->team, $actor, IncidentActionType::FreezeApplication, $application, $application->name, $reason, $application->environment);
    }

    /**
     * Lift an application's runtime freeze and mark the originating freeze
     * reverted, resuming normal operation.
     */
    public function liftApplicationFreeze(Application $application, User $actor, string $reason): IncidentAction
    {
        $application->loadMissing('team');
        $application->update(['runtime_frozen_at' => null, 'runtime_frozen_by' => null]);

        $application->team->incidentActions()
            ->where('type', IncidentActionType::FreezeApplication)
            ->where('subject_id', $application->id)
            ->whereNull('reverted_at')
            ->latest()
            ->first()
            ?->update(['reverted_at' => Date::now(), 'reverted_by' => $actor->getAuthIdentifier()]);

        return $this->record($application->team, $actor, IncidentActionType::LiftFreeze, $application, $application->name, $reason, $application->environment);
    }

    /**
     * Record the incident timeline entry and its high-severity audit event.
     */
    private function record(Team $team, User $actor, IncidentActionType $type, Model $subject, string $label, string $reason, ?Environment $environment): IncidentAction
    {
        $incident = $team->incidentActions()->create([
            'actor_user_id' => $actor->getAuthIdentifier(),
            'actor_label' => $actor->name,
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'subject_label' => $label,
            'reason' => $reason,
            'environment' => $environment,
        ]);

        AuditEvent::create([
            'team_id' => $team->id,
            'actor_user_id' => $actor->getAuthIdentifier(),
            'actor_label' => $actor->name,
            'action' => 'incident.'.$type->value,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => (string) $subject->getKey(),
            'environment' => $environment,
            'metadata' => ['reason' => $reason, 'severity' => $type->severity()->value],
            'ip_address' => request()->ip(),
        ]);

        return $incident;
    }
}
