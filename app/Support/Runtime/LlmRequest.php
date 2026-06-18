<?php

namespace App\Support\Runtime;

/**
 * An immutable request to the LLM Router for one conversation turn: the
 * resolved provider/model, runtime settings, the full message history, and the
 * tool definitions the model may request.
 */
final readonly class LlmRequest
{
    /**
     * @param  array<int, LlmMessage>  $messages
     * @param  array<int, LlmToolDefinition>  $tools
     */
    public function __construct(
        public string $providerDriver,
        public string $modelCode,
        public string $systemPrompt,
        public array $messages,
        public array $tools = [],
        public float $temperature = 0.7,
        public int $maxTokens = 1024,
        public int $timeoutSeconds = 30,
    ) {}
}
