<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Whether a registered webhook endpoint currently receives deliveries. A
 * disabled endpoint is retained (with its delivery history) but skipped when
 * MAACC emits run events.
 */
enum WebhookEndpointStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Get the human-readable label for the endpoint status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the endpoint should receive deliveries.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get all statuses as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
