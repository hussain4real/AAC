<?php

namespace App\Enums;

use App\Models\QuotaLimit;
use Illuminate\Support\Str;

/**
 * The dimension a {@see QuotaLimit} applies to. A quota may also be
 * narrowed to a single {@see Environment}, giving the BRS matrix of limits "by
 * application, project, agent, model, and environment".
 */
enum QuotaScope: string
{
    case Platform = 'platform';
    case Application = 'application';
    case Project = 'project';
    case Agent = 'agent';
    case Model = 'model';

    /**
     * Get the display label for the scope (e.g. "Application").
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Determine whether the scope targets a specific subject record (and so
     * requires a `subject_id`), as opposed to the whole platform/team.
     */
    public function requiresSubject(): bool
    {
        return $this !== self::Platform;
    }

    /**
     * Get all scopes as value/label option pairs.
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
