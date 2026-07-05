<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookEventType;
use App\Models\AgentRun;
use App\Support\Runtime\RunPayload;
use Illuminate\Support\Facades\Date;

/**
 * Builds the JSON body MAACC posts to a webhook endpoint for a run event. It
 * wraps the same {@see RunPayload} envelope the SDK/runtime API returns, so a
 * webhook receiver sees an identical run shape to a polled response.
 */
class WebhookPayload
{
    /**
     * Build the webhook body for the given run and event.
     *
     * @return array<string, mixed>
     */
    public static function for(AgentRun $run, WebhookEventType $event): array
    {
        return [
            'event' => $event->value,
            'occurred_at' => Date::now()->toIso8601String(),
            'data' => RunPayload::for($run),
        ];
    }
}
