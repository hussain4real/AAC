<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\WebhookEndpointStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreWebhookEndpointRequest;
use App\Http\Requests\Maacc\UpdateWebhookEndpointRequest;
use App\Models\Application;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookEndpointVerifier;
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
            'status' => WebhookEndpointStatus::PendingVerification,
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

        $data = $request->validated();

        if (isset($data['url']) && $data['url'] !== $webhookEndpoint->url) {
            $data['status'] = WebhookEndpointStatus::PendingVerification;
        }

        $webhookEndpoint->update($data);

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
        $webhookEndpoint->rotateSecret($secret);
        $webhookEndpoint->status = WebhookEndpointStatus::PendingVerification;
        $webhookEndpoint->save();

        $this->flashSecret($webhookEndpoint, $secret);

        return back();
    }

    /**
     * Send a signed probe and activate only when the destination acknowledges it.
     */
    public function verify(string $currentTeam, WebhookEndpoint $webhookEndpoint, WebhookEndpointVerifier $verifier): RedirectResponse
    {
        Gate::authorize('update', $webhookEndpoint);

        $verified = $verifier->verify($webhookEndpoint);
        Inertia::flash('toast', [
            'type' => $verified ? 'success' : 'error',
            'message' => $verified
                ? 'Webhook test delivered; endpoint activated.'
                : 'Webhook test failed; endpoint remains pending verification.',
        ]);

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
