<?php

namespace App\Enums;

use App\Support\Observability\OperationalMonitor;

/**
 * Severity of an operational alert surfaced by the
 * {@see OperationalMonitor}. Raw values match the
 * console contract (resources/js/maac/data.ts alert `sev`).
 */
enum AlertSeverity: string
{
    case Low = 'low';
    case Medium = 'med';
    case High = 'high';

    /**
     * Get the human-readable label for the severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    /**
     * Rank the severity so alerts can be ordered most-urgent first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
