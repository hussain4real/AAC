<?php

namespace App\Support\Runtime;

use App\Enums\LlmVerificationOutcome;

/**
 * The result of a live LLM provider connection check: the classified outcome, a
 * human-readable message (the outcome's guidance, enriched with the provider's
 * own error text when available), and the round-trip latency on success.
 */
final readonly class LlmVerificationResult
{
    public function __construct(
        public LlmVerificationOutcome $outcome,
        public string $message,
        public ?int $latencyMs = null,
    ) {}

    /**
     * Whether the provider connected successfully.
     */
    public function passed(): bool
    {
        return $this->outcome->isSuccess();
    }

    /**
     * The console-facing representation of the result.
     *
     * @return array{outcome: string, label: string, focus: string, message: string, passed: bool, latency_ms: int|null}
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'label' => $this->outcome->label(),
            'focus' => $this->outcome->focus(),
            'message' => $this->message,
            'passed' => $this->passed(),
            'latency_ms' => $this->latencyMs,
        ];
    }
}
