<?php

namespace App\Enums;

use App\Models\SsoConnection;
use Illuminate\Support\Str;

/**
 * Whether an enterprise {@see SsoConnection} accepts logins. A disabled
 * connection is hidden from the login screen and rejects the redirect/callback.
 */
enum SsoConnectionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the connection currently accepts logins.
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
