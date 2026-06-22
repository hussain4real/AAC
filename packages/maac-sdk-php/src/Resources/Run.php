<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * An agent run as reported by the runtime API. The populated fields depend on
 * {@see self::$status}: a completed run carries {@see self::$response}, a paused
 * run carries {@see self::$toolCall}, and a terminal failure carries
 * {@see self::$error}.
 */
final class Run
{
    public const STATUS_WAITING = 'waiting_for_client';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $runId,
        public readonly string $agentSlug,
        public readonly string $status,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly float $cost,
        public readonly ?string $response = null,
        public readonly ?ToolCall $toolCall = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $toolCall = is_array($data['tool_call'] ?? null) ? ToolCall::fromArray($data['tool_call']) : null;

        return new self(
            runId: (string) ($data['run_id'] ?? ''),
            agentSlug: (string) ($data['agent_slug'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            tokensIn: (int) ($usage['tokens_in'] ?? 0),
            tokensOut: (int) ($usage['tokens_out'] ?? 0),
            cost: (float) ($data['cost'] ?? 0),
            response: is_string($data['response'] ?? null) ? $data['response'] : null,
            toolCall: $toolCall,
            error: is_string($data['error'] ?? null) ? $data['error'] : null,
        );
    }

    /**
     * Whether the run is paused awaiting a client-side tool result.
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * Whether the run finished successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Whether the run reached a terminal status (completed or otherwise) and
     * will not change again.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
