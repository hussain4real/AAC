<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookSigner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Delivers a single run-event payload to a webhook endpoint with an
 * HMAC-SHA256 signature, recording every attempt on the {@see WebhookDelivery}.
 * It owns its own retry policy (configurable attempts + exponential backoff via
 * a delayed self-dispatch) and never throws into the run path, so a slow or
 * failing endpoint can never affect the agent run it describes. Each attempt's
 * outcome is persisted, making failures observable and the delivery replayable.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * Execute the job: sign and POST the payload, then record the outcome and
     * schedule a backoff retry if the endpoint did not acknowledge.
     */
    public function handle(): void
    {
        $delivery = $this->delivery->fresh();

        // The delivery may have been removed (cascading from its endpoint)
        // between enqueue and execution — nothing to deliver.
        if ($delivery === null) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint->isActive()) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'The webhook endpoint is disabled.',
                'last_attempted_at' => Date::now(),
                'next_attempt_at' => null,
            ]);

            return;
        }

        $attempt = $delivery->attempts + 1;
        $body = (string) json_encode($delivery->payload);
        $timestamp = (string) Date::now()->getTimestamp();
        $signature = WebhookSigner::sign($body, $timestamp, $endpoint->secret);

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders([
                    'X-Maacc-Webhook-Event' => $delivery->event->value,
                    'X-Maacc-Webhook-Delivery' => $delivery->id,
                    'X-Maacc-Webhook-Timestamp' => $timestamp,
                    'X-Maacc-Signature' => WebhookSigner::header($signature),
                    'User-Agent' => 'MAACC-Webhooks/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $failed = $response->failed();
            $status = $response->status();
            $responseBody = Str::limit($response->body(), 2000);
            $error = $failed ? "The endpoint returned HTTP {$status}." : null;
        } catch (ConnectionException $exception) {
            $failed = true;
            $status = null;
            $responseBody = null;
            $error = $exception->getMessage();
        }

        if (! $failed) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Delivered,
                'attempts' => $attempt,
                'signature' => $signature,
                'response_status' => $status,
                'response_body' => $responseBody,
                'error' => null,
                'delivered_at' => Date::now(),
                'last_attempted_at' => Date::now(),
                'next_attempt_at' => null,
            ]);

            $endpoint->update(['last_delivered_at' => Date::now()]);

            return;
        }

        $this->recordFailure($delivery, $endpoint, $attempt, $signature, $status, $responseBody, $error ?? 'Delivery failed.');
    }

    /**
     * Persist a failed attempt and schedule a backoff retry, or mark the
     * delivery permanently failed once it has exhausted its attempts.
     */
    private function recordFailure(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $attempt, string $signature, ?int $status, ?string $responseBody, string $error): void
    {
        $willRetry = $attempt < $this->maxAttempts();

        $delivery->update([
            'status' => $willRetry ? WebhookDeliveryStatus::Pending : WebhookDeliveryStatus::Failed,
            'attempts' => $attempt,
            'signature' => $signature,
            'response_status' => $status,
            'response_body' => $responseBody,
            'error' => $error,
            'last_attempted_at' => Date::now(),
            'next_attempt_at' => $willRetry ? Date::now()->addSeconds($this->backoffFor($attempt)) : null,
        ]);

        if ($willRetry) {
            self::dispatch($delivery)->delay($this->backoffFor($attempt))->onQueue('webhooks');

            return;
        }

        $endpoint->update(['last_failed_at' => Date::now()]);
    }

    /**
     * The backoff delay, in seconds, before retrying after the given attempt.
     */
    private function backoffFor(int $attempt): int
    {
        $schedule = config('maacc.runtime.webhooks.backoff');
        $schedule = is_array($schedule) && $schedule !== [] ? array_values($schedule) : [10, 30, 60, 120];

        return (int) ($schedule[$attempt - 1] ?? $schedule[count($schedule) - 1]);
    }

    /**
     * The maximum number of delivery attempts.
     */
    private function maxAttempts(): int
    {
        return max(1, (int) config('maacc.runtime.webhooks.max_attempts', 5));
    }

    /**
     * The per-attempt HTTP timeout, in seconds.
     */
    private function timeout(): int
    {
        return max(1, (int) config('maacc.runtime.webhooks.timeout_seconds', 10));
    }
}
