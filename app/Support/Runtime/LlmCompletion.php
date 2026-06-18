<?php

namespace App\Support\Runtime;

use App\Enums\LlmFinishReason;

/**
 * The result of one LLM Router turn: either a final text answer or a request to
 * execute a named tool with the given arguments, always accompanied by usage.
 */
final readonly class LlmCompletion
{
    /**
     * @param  array<string, mixed>|null  $toolArguments
     */
    public function __construct(
        public LlmFinishReason $finishReason,
        public LlmUsage $usage,
        public ?string $text = null,
        public ?string $toolName = null,
        public ?array $toolArguments = null,
    ) {}

    /**
     * Build a completion representing a final text answer.
     */
    public static function text(string $text, LlmUsage $usage): self
    {
        return new self(LlmFinishReason::Stop, $usage, text: $text);
    }

    /**
     * Build a completion representing a requested tool call.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function toolCall(string $toolName, array $arguments, LlmUsage $usage): self
    {
        return new self(LlmFinishReason::ToolCall, $usage, toolName: $toolName, toolArguments: $arguments);
    }

    /**
     * Determine whether the model requested a tool call.
     */
    public function isToolCall(): bool
    {
        return $this->finishReason === LlmFinishReason::ToolCall;
    }
}
