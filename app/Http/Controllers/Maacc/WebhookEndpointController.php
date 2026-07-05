<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\WebhookEndpointStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreWebhookEndpointRequest;
use App\Http\Requests\Maacc\UpdateWebhookEndpointRequest;
use App\Models\Application;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of webhook endpoints: register (with a one-time signing
 * secret), update destination/events/status, rotate the secret, and remove.
 */
class WebhookEndpointController extends Controller
{
    /**
     * Register a webhook endpoint and flash its one-time signing secret.
     */
    public function store(StoreWebhookEndpointRequest $request): RedirectResponse
    {
        Gate::authorize('create', WebhookEndpoint::class);

        /** @var Application $application */
        $application = Application::query()->findOrFail($request->validated('application_id'));

        $secret = WebhookEndpoint::generateSecret();

        $endpoint = new WebhookEndpoint([
            'application_id' => $application->id,
            'environment' => $request->environment(),
            'url' => $request->webhookUrl(),
            'events' => $request->events(),
            'description' => $request->description(),
            'status' => WebhookEndpointStatus::Active,
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $endpoint->fillSecret($secret);
        $endpoint->save();

        $this->flashSecret($endpoint, $secret);

        return back();
    }

    /**
     * Update the endpoint's destination, events, description, or status.
     */
    public function update(UpdateWebhookEndpointRequest $request, string $currentTeam, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        Gate::authorize('update', $webhookEndpoint);

        $webhookEndpoint->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Webhook endpoint updated.']);

        return back();
    }

    /**
     * Rotate the endpoint's signing secret, re-displaying it once.
     */
    public function rotate(string $currentTeam, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        Gate::authorize('rotate', $webhookEndpoint);

        $secret = WebhookEndpoint::generateSecret();
        $webhookEndpoint->fillSecret($secret);
        $webhookEndpoint->save();

        $this->flashSecret($webhookEndpoint, $secret);

        return back();
    }

    /**
     * Delete the endpoint and its delivery history.
     */
    public function destroy(Request $request, string $currentTeam, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        Gate::authorize('delete', $webhookEndpoint);

        $webhookEndpoint->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Webhook endpoint removed.']);

        return back();
    }

    /**
     * Flash the one-time plaintext signing secret for display.
     */
    private function flashSecret(WebhookEndpoint $endpoint, string $secret): void
    {
        Inertia::flash('webhookSecret', [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'secret' => $secret,
        ]);
    }
}
