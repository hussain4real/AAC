<?php

namespace App\Support\Runtime;

/**
 * A single message in the conversation passed to the LLM Router. Roles mirror
 * the chat-completion convention (`user`, `assistant`, `tool`); a `tool`
 * message also carries the name of the tool whose result it represents.
 */
final readonly class LlmMessage
{
    public function __construct(
        public string $role,
        public string $content,
        public ?string $toolName = null,
    ) {}

    /**
     * Build a message representing the caller's input.
     */
    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    /**
     * Build a message representing the model's own prior turn.
     */
    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /**
     * Build a message carrying the result of a previously requested tool call.
     */
    public static function tool(string $toolName, string $content): self
    {
        return new self('tool', $content, $toolName);
    }

    /**
     * Rehydrate a message from its persisted run-state array.
     *
     * @param  array<array-key, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        return new self(
            (string) $state['role'],
            (string) $state['content'],
            isset($state['tool_name']) ? (string) $state['tool_name'] : null,
        );
    }

    /**
     * Serialize the message for storage in the run state.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'tool_name' => $this->toolName,
        ];
    }
}
