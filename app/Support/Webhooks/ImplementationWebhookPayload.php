<?php

namespace App\Support\Webhooks;

use App\Enums\Environment;
use App\Enums\WebhookEventType;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Support\Facades\Date;

/**
 * Builds the JSON body MAACC posts for a tool-implementation webhook event, so a
 * receiver learns which client-side tool was reported, in which environment, and
 * the resolved implementation status (implemented, outdated, or incompatible).
 */
class ImplementationWebhookPayload
{
    /**
     * Build the webhook body for a reported implementation.
     *
     * @return array<string, mixed>
     */
    public static function for(WebhookEventType $event, Environment $environment, ToolContract $contract, ToolImplementation $implementation): array
    {
        return [
            'event' => $event->value,
            'occurred_at' => Date::now()->toIso8601String(),
            'data' => [
                'tool' => $contract->slug,
                'environment' => $environment->value,
                'status' => $implementation->status->value,
                'implemented_version' => $implementation->implemented_version,
                'contract_version' => $contract->version,
                'handler_name' => $implementation->handler_name,
            ],
        ];
    }
}
