<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console replay of failed webhook deliveries: resets the attempt counter and
 * re-queues the delivery so a transient endpoint failure can be recovered from.
 */
class WebhookDeliveryController extends Controller
{
    /**
     * Replay a failed delivery.
     */
    public function replay(Request $request, string $currentTeam, WebhookDelivery $webhookDelivery): RedirectResponse
    {
        Gate::authorize('replay', $webhookDelivery);

        if (! $webhookDelivery->isReplayable()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only failed deliveries can be replayed.']);

            return back();
        }

        $webhookDelivery->update([
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'processing_token' => null,
            'processing_claimed_at' => null,
            'replay_count' => $webhookDelivery->replay_count + 1,
            'secret_version' => $webhookDelivery->endpoint->secret_version,
            'error' => null,
            'response_status' => null,
            'response_body' => null,
            'next_attempt_at' => null,
            'delivered_at' => null,
        ]);

        DeliverWebhook::dispatch($webhookDelivery)->onQueue('webhooks');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery re-queued.']);

        return back();
    }
}
