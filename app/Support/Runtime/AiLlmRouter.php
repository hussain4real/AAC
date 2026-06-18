<?php

namespace App\Support\Runtime;

use App\Support\Runtime\Contracts\LlmRouter;
use Laravel\Ai\AnonymousAgent;

/**
 * The production LLM Router, backed by the Laravel AI SDK (`laravel/ai`).
 *
 * MAAC must own the orchestration loop so it can pause for client-side tools,
 * which the SDK's auto-executing agent loop cannot do. This router therefore
 * asks the model for a single turn and surfaces tool requests through a compact
 * JSON protocol embedded in the instructions: the model either replies with a
 * `{"tool": ..., "arguments": ...}` envelope (a tool call) or with plain text
 * (a final answer). The MAAC runtime decides how to route any requested tool.
 */
class AiLlmRouter implements LlmRouter
{
    /**
     * Produce the next conversation turn via the configured AI provider.
     */
    public function complete(LlmRequest $request): LlmCompletion
    {
        $agent = new AnonymousAgent($this->instructions($request), [], []);

        $response = $agent->prompt(
            $this->transcript($request->messages),
            provider: $request->providerDriver,
            model: $request->modelCode,
            timeout: $request->timeoutSeconds,
        );

        $usage = new LlmUsage(
            $response->usage->promptTokens,
            $response->usage->completionTokens,
        );

        return $this->parse(trim($response->text), $request->tools, $usage);
    }

    /**
     * Interpret the model's reply as either a tool-call envelope or final text.
     *
     * @param  array<int, LlmToolDefinition>  $tools
     */
    private function parse(string $text, array $tools, LlmUsage $usage): LlmCompletion
    {
        $decoded = json_decode($text, true);
        $names = array_map(fn (LlmToolDefinition $tool): string => $tool->name, $tools);

        if (is_array($decoded) && isset($decoded['tool']) && is_string($decoded['tool']) && in_array($decoded['tool'], $names, true)) {
            $arguments = isset($decoded['arguments']) && is_array($decoded['arguments']) ? $decoded['arguments'] : [];

            return LlmCompletion::toolCall($decoded['tool'], $arguments, $usage);
        }

        return LlmCompletion::text($text, $usage);
    }

    /**
     * Compose the system instructions, including the tool-call protocol when the
     * agent has tools available.
     */
    private function instructions(LlmRequest $request): string
    {
        if ($request->tools === []) {
            return $request->systemPrompt;
        }

        return $request->systemPrompt."\n\n".$this->toolProtocol($request->tools);
    }

    /**
     * Build the tool-call protocol preamble describing the available tools.
     *
     * @param  array<int, LlmToolDefinition>  $tools
     */
    private function toolProtocol(array $tools): string
    {
        $catalog = array_map(
            fn (LlmToolDefinition $tool): string => sprintf(
                '- %s: %s (arguments: %s)',
                $tool->name,
                $tool->description,
                json_encode($tool->inputSchema),
            ),
            $tools,
        );

        return implode("\n", [
            'You may call a tool to gather information. To call a tool, respond with ONLY a JSON',
            'object of the form {"tool": "<name>", "arguments": { ... }} and nothing else.',
            'When you have enough information, respond with the final answer as plain text.',
            'Available tools:',
            ...$catalog,
        ]);
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
