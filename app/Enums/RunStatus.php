<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Lifecycle status of an agent run. Raw values match the console contract
 * (resources/js/maac/data.ts); `requires_approval` is included for the
 * Phase 4 runtime and is not yet produced by the seeded fixture.
 */
enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case RequiresTool = 'requires_tool';
    case WaitingForClient = 'waiting_for_client';
    case RequiresApproval = 'requires_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the run status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Determine whether the run has reached a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Expired, self::Cancelled], true);
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
