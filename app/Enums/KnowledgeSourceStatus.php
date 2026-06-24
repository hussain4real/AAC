<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Lifecycle of a registered knowledge (RAG) source. A source starts as a draft
 * while its documents are ingested/indexed; it must be active before the runtime
 * may retrieve from it. A sensitive source (or one explicitly flagged) is gated
 * behind an ingestion approval and stays a draft until that approval is granted.
 * A disabled source is retained (with its index) but cannot back a retrieval.
 */
enum KnowledgeSourceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Get the human-readable label for the source status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the source may be retrieved from by the runtime.
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
