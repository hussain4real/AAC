<?php

namespace App\Support\Governance;

use App\Enums\Sensitivity;

/**
 * Redacts sensitive run payloads (prompts, responses, tool arguments, tool
 * results) before they are persisted. Confidential payloads are masked;
 * Restricted payloads are blocked entirely when raw logging is disallowed.
 * Public/Internal payloads are stored verbatim.
 */
class PayloadMasker
{
    /**
     * Replacement for a masked (Confidential) value.
     */
    public const REDACTED = '••• redacted •••';

    /**
     * Replacement for a blocked (Restricted) value.
     */
    public const BLOCKED = '••• blocked (restricted) •••';

    /**
     * Mask a text payload according to its sensitivity and the configured
     * masking/blocking behavior.
     */
    public function maskText(?string $value, bool $maskingEnabled, Sensitivity $sensitivity, bool $blockRestricted): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($sensitivity === Sensitivity::Restricted && $blockRestricted) {
            return self::BLOCKED;
        }

        if ($maskingEnabled && $sensitivity->requiresMasking()) {
            return self::REDACTED;
        }

        return $value;
    }

    /**
     * Mask a structured payload, preserving keys/structure while redacting the
     * scalar leaf values.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public function maskArray(?array $value, bool $maskingEnabled, Sensitivity $sensitivity, bool $blockRestricted): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($sensitivity === Sensitivity::Restricted && $blockRestricted) {
            return ['_redacted' => self::BLOCKED];
        }

        if ($maskingEnabled && $sensitivity->requiresMasking()) {
            return $this->redactLeaves($value);
        }

        return $value;
    }

    /**
     * Determine whether the given sensitivity would have any payload masked or
     * blocked under the supplied flags.
     */
    public function wouldRedact(Sensitivity $sensitivity, bool $maskingEnabled, bool $blockRestricted): bool
    {
        if ($sensitivity === Sensitivity::Restricted && $blockRestricted) {
            return true;
        }

        return $maskingEnabled && $sensitivity->requiresMasking();
    }

    /**
     * Recursively replace scalar leaf values with the redaction placeholder.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function redactLeaves(array $value): array
    {
        return array_map(
            fn (mixed $item): mixed => is_array($item) ? $this->redactLeaves($item) : self::REDACTED,
            $value,
        );
    }
}
