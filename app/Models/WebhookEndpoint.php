<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\Environment;
use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use Carbon\CarbonInterface;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An application-registered destination for run lifecycle webhooks. The signing
 * `secret` is encrypted at rest (decrypted on read so deliveries can be signed)
 * and shown to the registrant only once.
 *
 * @property string $id
 * @property string $application_id
 * @property Environment $environment
 * @property string $url
 * @property string $secret
 * @property string|null $previous_secret
 * @property CarbonInterface|null $previous_secret_expires_at
 * @property int $secret_version
 * @property int $next_delivery_sequence
 * @property string|null $last_four
 * @property array<int, string> $events
 * @property string|null $description
 * @property WebhookEndpointStatus $status
 * @property int|null $created_by
 * @property Carbon|null $last_delivered_at
 * @property Carbon|null $last_failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Application $application
 * @property-read User|null $creator
 * @property-read Collection<int, WebhookDelivery> $deliveries
 */
#[Fillable(['application_id', 'environment', 'url', 'secret', 'previous_secret', 'previous_secret_expires_at', 'secret_version', 'next_delivery_sequence', 'last_four', 'events', 'description', 'status', 'created_by', 'last_delivered_at', 'last_failed_at'])]
#[Hidden(['secret', 'previous_secret'])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /** @var array<string, int> */
    protected $attributes = [
        'secret_version' => 1,
        'next_delivery_sequence' => 1,
    ];

    /**
     * Get the application that owns the endpoint.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user that registered the endpoint.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the delivery attempts made to the endpoint.
     *
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Generate a new plaintext signing secret (shown to the registrant once).
     */
    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /**
     * Store the given plaintext signing secret (encrypted at rest), retaining
     * the last characters for display.
     */
    public function fillSecret(string $plainSecret): void
    {
        $this->secret = $plainSecret;
        $this->last_four = substr($plainSecret, -6);
    }

    /**
     * Rotate with a bounded overlap so in-flight deliveries can still be
     * verified with the prior secret while all new deliveries use the new one.
     */
    public function rotateSecret(string $plainSecret): void
    {
        $this->previous_secret = $this->secret;
        $this->previous_secret_expires_at = now()->addSeconds((int) config('maacc.runtime.webhooks.secret_rotation_overlap_seconds', 86400));
        $this->secret_version = $this->secret_version + 1;
        $this->fillSecret($plainSecret);
    }

    public function signingSecretFor(int $version): ?string
    {
        if ($version === $this->secret_version) {
            return $this->secret;
        }

        if ($version === $this->secret_version - 1 && $this->previous_secret_expires_at?->isFuture()) {
            return $this->previous_secret;
        }

        return null;
    }

    /**
     * Whether the endpoint currently receives deliveries.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the endpoint is subscribed to the given event type. An endpoint
     * subscribed to the `*` wildcard receives every event.
     */
    public function subscribesTo(WebhookEventType $event): bool
    {
        return in_array('*', $this->events, true)
            || in_array($event->value, $this->events, true);
    }

    /**
     * Resolve the team this endpoint is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->application->team;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => Environment::class,
            'status' => WebhookEndpointStatus::class,
            'events' => 'array',
            'secret' => 'encrypted',
            'previous_secret' => 'encrypted',
            'previous_secret_expires_at' => 'datetime',
            'secret_version' => 'integer',
            'next_delivery_sequence' => 'integer',
            'last_delivered_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }
}
