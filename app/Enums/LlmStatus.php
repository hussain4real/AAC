<?php

namespace App\Enums;

/**
 * Governance status of an approved LLM in the catalog.
 */
enum LlmStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Deprecated = 'deprecated';
    case Blocked = 'blocked';

    /**
     * Get the display label for the status (matches the console contract casing).
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether the model is live and usable by agents. Only an approved model
     * runs; a draft awaits a passing verification before it can be published.
     */
    public function isPublished(): bool
    {
        return $this === self::Approved;
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
