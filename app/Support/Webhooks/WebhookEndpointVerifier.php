<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookEndpointStatus;
use App\Exceptions\OutboundRequestBlocked;
use App\Models\WebhookEndpoint;
use App\Support\Outbound\OutboundHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class WebhookEndpointVerifier
{
    public function __construct(private readonly OutboundHttpClient $http) {}

    /**
     * Send a signed test event and activate only after a successful response.
     */
    public function verify(WebhookEndpoint $endpoint): bool
    {
        $timestamp = (string) Date::now()->getTimestamp();
        $deliveryId = 'test_'.Str::lower((string) Str::ulid());
        $body = (string) json_encode([
            'id' => $deliveryId,
            'type' => 'webhook.test',
            'created_at' => Date::now()->toIso8601String(),
            'data' => ['endpoint_id' => $endpoint->id],
        ], JSON_THROW_ON_ERROR);
        $signature = WebhookSigner::sign($body, $timestamp, $endpoint->secret);

        try {
            $response = $this->http->send('webhook', 'POST', $endpoint->url, [
                'headers' => [
                    'X-Maacc-Webhook-Event' => 'webhook.test',
                    'X-Maacc-Webhook-Delivery' => $deliveryId,
                    'X-Maacc-Webhook-Timestamp' => $timestamp,
                    'X-Maacc-Signature' => WebhookSigner::header($signature),
                    'User-Agent' => 'MAACC-Webhooks/1.0',
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
                'timeout' => max(1, (int) config('maacc.runtime.webhooks.timeout_seconds', 10)),
                'connect_timeout' => max(1, (int) config('maacc.runtime.webhooks.connect_timeout_seconds', 3)),
                'max_redirects' => 0,
            ]);
        } catch (ConnectionException|OutboundRequestBlocked) {
            $endpoint->update(['status' => WebhookEndpointStatus::PendingVerification]);

            return false;
        }

        $verified = $response->successful();
        $endpoint->update([
            'status' => $verified
                ? WebhookEndpointStatus::Active
                : WebhookEndpointStatus::PendingVerification,
        ]);

        return $verified;
    }
}
