<?php

namespace App\Support\Runtime;

use App\Support\Runtime\Contracts\LlmRouter;
use InvalidArgumentException;
use Laravel\Ai\Ai;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * The production LLM Router, backed by the Laravel AI SDK (`laravel/ai`).
 *
 * MAACC owns the orchestration loop so it can pause for client-side tools, which
 * the SDK's auto-executing agent loop cannot. The router therefore drives a
 * {@see RuntimeAgent} capped at a single step: it offers the agent's tools as
 * native provider function-calls (which models follow reliably) and surfaces any
 * tool the model requests back to MAACC un-executed, for MAACC to route by
 * execution mode. A plain reply is returned as the final answer. A legacy
 * text-protocol envelope ({@see self::parse()}) is still honored as a fallback.
 */
class AiLlmRouter implements LlmRouter
{
    /**
     * Produce the next conversation turn via the configured AI provider.
     */
    public function complete(LlmRequest $request): LlmCompletion
    {
        $this->applyVaultKey($request);

        $tools = $this->offersTools($request)
            ? [
                ...array_map(fn (LlmToolDefinition $tool): RuntimeTool => new RuntimeTool($tool), $request->tools),
                ...array_map(fn (LlmProviderToolDefinition $tool): ProviderTool => $this->providerTool($tool), $request->providerTools),
            ]
            : [];

        $response = (new RuntimeAgent($request->systemPrompt, $tools))->prompt(
            $this->transcript($request->messages),
            provider: $request->providerDriver,
            model: $request->modelCode,
            timeout: $request->timeoutSeconds,
        );

        $usage = new LlmUsage(
            $response->usage->promptTokens,
            $response->usage->completionTokens,
        );

        $names = array_map(fn (LlmToolDefinition $tool): string => $tool->name, $request->tools);
        $call = $response->toolCalls->first();

        if ($call instanceof ToolCall && in_array($call->name, $names, true)) {
            return LlmCompletion::toolCall($call->name, $call->arguments, $usage);
        }

        return $this->parse(trim($response->text), $request->tools, $usage);
    }

    /**
     * Convert MAACC's provider-tool definition into the Laravel AI SDK tool.
     */
    private function providerTool(LlmProviderToolDefinition $definition): ProviderTool
    {
        return match ($definition->type) {
            LlmProviderToolDefinition::WEB_SEARCH => new WebSearch,
            default => throw new InvalidArgumentException("Unsupported provider-hosted tool type [{$definition->type}]."),
        };
    }

    /**
     * Whether to offer the agent's tools to the model on this turn. Tools are
     * withheld immediately after a tool result so the model produces the final
     * answer from it rather than requesting the same tool again.
     */
    private function offersTools(LlmRequest $request): bool
    {
        if ($request->tools === [] && $request->providerTools === []) {
            return false;
        }

        $messages = $request->messages;
        $last = end($messages);

        return ! ($last instanceof LlmMessage && $last->role === 'tool');
    }

    /**
     * Override the provider API key for this call with the vault-resolved key,
     * when one is bound. This is what makes a vault rotation take effect on the
     * very next run without redeploying — the key is never read from disk.
     */
    private function applyVaultKey(LlmRequest $request): void
    {
        if ($request->apiKey !== null) {
            config(["ai.providers.{$request->providerDriver}.key" => $request->apiKey]);

            // The AI manager memoizes a provider with the key it was first
            // resolved with, so drop the cached instance to force it to be
            // rebuilt with the freshly applied key. This is what guarantees a
            // vault rotation takes effect on the very next turn, even inside a
            // long-lived worker that already used the provider.
            Ai::forgetInstance($request->providerDriver);
        }
    }

    /**
     * Interpret a plain-text reply as either a final answer or a legacy tool-call
     * envelope. A model may wrap the envelope in a Markdown code fence, so the
     * candidate is unwrapped before decoding; anything that is not a recognized
     * tool envelope is returned verbatim as the final answer.
     *
     * @param  array<int, LlmToolDefinition>  $tools
     */
    private function parse(string $text, array $tools, LlmUsage $usage): LlmCompletion
    {
        $decoded = json_decode($this->unwrap($text), true);
        $names = array_map(fn (LlmToolDefinition $tool): string => $tool->name, $tools);

        if (is_array($decoded) && isset($decoded['tool']) && is_string($decoded['tool']) && in_array($decoded['tool'], $names, true)) {
            $arguments = isset($decoded['arguments']) && is_array($decoded['arguments']) ? $decoded['arguments'] : [];

            return LlmCompletion::toolCall($decoded['tool'], $arguments, $usage);
        }

        return LlmCompletion::text($text, $usage);
    }

    /**
     * Strip a wrapping Markdown code fence (```` ```json … ``` ````) from the
     * reply so a fenced tool-call envelope still decodes. Non-fenced text is
     * returned trimmed and unchanged.
     */
    private function unwrap(string $text): string
    {
        $trimmed = trim($text);

        if (! str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $inner = preg_replace('/^```[a-zA-Z0-9]*\n?|\n?```$/', '', $trimmed);

        return trim($inner ?? $trimmed);
    }

    /**
     * Render the conversation history into a single prompt for the model.
     *
     * @param  array<int, LlmMessage>  $messages
     */
    private function transcript(array $messages): string
    {
        $lines = array_map(function (LlmMessage $message): string {
            $label = match ($message->role) {
                'assistant' => 'Assistant',
                'tool' => 'Tool result ('.$message->toolName.')',
                default => 'User',
            };

            return $label.': '.$message->content;
        }, $messages);

        return implode("\n\n", $lines);
    }
}
