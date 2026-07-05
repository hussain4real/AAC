<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\IncidentActionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreIncidentRequest;
use App\Models\Application;
use App\Models\Credential;
use App\Models\IncidentAction;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Support\Governance\BreakGlassManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console entry point for break-glass / incident-response controls. A single
 * audited action immediately contains an incident (revoke a credential, disable a
 * model, shut down a connector, suspend a webhook, or freeze/unfreeze an
 * application's runtime), recording the operator's mandatory reason.
 */
class IncidentController extends Controller
{
    /**
     * Trigger a break-glass control against the resolved subject.
     */
    public function store(StoreIncidentRequest $request, BreakGlassManager $breakGlass): RedirectResponse
    {
        Gate::authorize('create', IncidentAction::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $user = $request->user();
        $target = (string) $request->validated('target');
        $reason = (string) $request->validated('reason');

        match (IncidentActionType::from($request->validated('action'))) {
            IncidentActionType::RevokeCredential => $breakGlass->revokeCredential($this->credential($team, $target), $user, $reason),
            IncidentActionType::DisableModel => $breakGlass->disableModel($this->model($team, $target), $user, $reason),
            IncidentActionType::ShutdownConnector => $breakGlass->shutdownConnector($this->connector($team, $target), $user, $reason),
            IncidentActionType::SuspendWebhook => $breakGlass->suspendWebhook($this->webhook($team, $target), $user, $reason),
            IncidentActionType::FreezeApplication => $breakGlass->freezeApplication($this->application($team, $target), $user, $reason),
            IncidentActionType::LiftFreeze => $breakGlass->liftApplicationFreeze($this->application($team, $target), $user, $reason),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Incident control applied.']);

        return back();
    }

    /**
     * Resolve a credential by id within the team.
     */
    private function credential(Team $team, string $reference): Credential
    {
        return Credential::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->whereKey($reference)
            ->firstOrFail();
    }

    /**
     * Resolve a model by slug within the team.
     */
    private function model(Team $team, string $reference): LlmProvider
    {
        return $team->llmProviders()->where('slug', $reference)->firstOrFail();
    }

    /**
     * Resolve a connector by slug within the team.
     */
    private function connector(Team $team, string $reference): McpConnector
    {
        return McpConnector::query()->where('team_id', $team->id)->where('slug', $reference)->firstOrFail();
    }

    /**
     * Resolve a webhook endpoint by id within the team.
     */
    private function webhook(Team $team, string $reference): WebhookEndpoint
    {
        return WebhookEndpoint::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->whereKey($reference)
            ->firstOrFail();
    }

    /**
     * Resolve an application by slug within the team.
     */
    private function application(Team $team, string $reference): Application
    {
        return $team->applications()->where('slug', $reference)->firstOrFail();
    }
}
