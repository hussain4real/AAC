<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Lifecycle of an evaluation run (a golden dataset executed against an agent).
 * It is queued (pending), driven (running), and resolves to passed or failed
 * based on whether every required case met its assertions. A promotion gate
 * reads this status: an agent with a required evaluation that is not passed
 * cannot be published.
 */
enum EvaluationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the evaluation status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the evaluation has finished (passed or failed).
     */
    public function isComplete(): bool
    {
        return $this === self::Passed || $this === self::Failed;
    }

    /**
     * Whether the evaluation met every required case assertion.
     */
    public function hasPassed(): bool
    {
        return $this === self::Passed;
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
