<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Exceptions\OutboundRequestBlocked;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Outbound\OutboundHttpClient;
use App\Support\Webhooks\WebhookSigner;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers a single run-event payload to a webhook endpoint with an
 * HMAC-SHA256 signature, recording every attempt on the {@see WebhookDelivery}.
 * It owns its own retry policy (configurable attempts + exponential backoff via
 * a delayed self-dispatch) and never throws into the run path, so a slow or
 * failing endpoint can never affect the agent run it describes. Each attempt's
 * outcome is persisted, making failures observable and the delivery replayable.
 */
class DeliverWebhook implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 20;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * Execute the job: sign and POST the payload, then record the outcome and
     * schedule a backoff retry if the endpoint did not acknowledge.
     */
    public function handle(OutboundHttpClient $http): void
    {
        $delivery = $this->delivery->fresh();

        // The delivery may have been removed (cascading from its endpoint)
        // between enqueue and execution — nothing to deliver.
        if ($delivery === null) {
            return;
        }

        $token = (string) Str::uuid();
        $staleBefore = Date::now()->subSeconds((int) config('maacc.runtime.webhooks.stale_claim_seconds', 180));
        $claimed = WebhookDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', WebhookDeliveryStatus::Pending)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('processing_token')->orWhere('processing_claimed_at', '<=', $staleBefore);
            })
            ->update(['processing_token' => $token, 'processing_claimed_at' => Date::now()]);

        if ($claimed !== 1) {
            return;
        }

        $delivery->refresh();

        $endpoint = $delivery->endpoint;

        if (! $endpoint->isActive()) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'The webhook endpoint is disabled.',
                'last_attempted_at' => Date::now(),
                'next_attempt_at' => null,
                'processing_token' => null,
                'processing_claimed_at' => null,
            ]);

            return;
        }

        $attempt = $delivery->attempts + 1;
        $body = (string) json_encode($delivery->payload);
        $timestamp = (string) Date::now()->getTimestamp();
        $secret = $endpoint->signingSecretFor($delivery->secret_version);

        if ($secret === null) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'The signing-key rotation overlap elapsed before delivery.',
                'last_attempted_at' => Date::now(),
                'processing_token' => null,
                'processing_claimed_at' => null,
            ]);

            return;
        }

        $signature = WebhookSigner::sign($body, $timestamp, $secret);

        try {
            $response = $http->send('webhook', 'POST', $endpoint->url, [
                'headers' => [
                    'X-Maacc-Webhook-Event' => $delivery->event->value,
                    'X-Maacc-Webhook-Delivery' => $delivery->id,
                    'X-Maacc-Webhook-Sequence' => (string) $delivery->event_sequence,
                    'X-Maacc-Webhook-Replay' => (string) $delivery->replay_count,
                    'X-Maacc-Webhook-Key-Version' => (string) $delivery->secret_version,
                    'X-Maacc-Webhook-Timestamp' => $timestamp,
                    'X-Maacc-Signature' => WebhookSigner::header($signature),
                    'User-Agent' => 'MAACC-Webhooks/1.0',
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
                'timeout' => $this->timeout(),
                'connect_timeout' => max(1, (int) config('maacc.runtime.webhooks.connect_timeout_seconds', 3)),
                'max_redirects' => 0,
            ]);

            $failed = ! $response->successful();
            $status = $response->status();
            $responseBody = null;
            $error = $failed ? "The endpoint returned HTTP {$status}." : null;
        } catch (ConnectionException|OutboundRequestBlocked) {
            $failed = true;
            $status = null;
            $responseBody = null;
            $error = 'The webhook endpoint could not be reached.';
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
                'processing_token' => null,
                'processing_claimed_at' => null,
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
            'processing_token' => null,
            'processing_claimed_at' => null,
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

    public function uniqueId(): string
    {
        return 'webhook-delivery:'.$this->delivery->id;
    }

    public function failed(?Throwable $exception): void
    {
        WebhookDelivery::query()->whereKey($this->delivery->id)->update([
            'processing_token' => null,
            'processing_claimed_at' => null,
            'error' => 'The webhook worker failed before completing the attempt.',
        ]);
    }
}
