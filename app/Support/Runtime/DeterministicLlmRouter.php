<?php

namespace App\Support\Runtime;

use App\Providers\RuntimeServiceProvider;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Sdk\ToolSchema;

/**
 * A deterministic, dependency-free {@see LlmRouter} for end-to-end validation
 * and local smoke runs: it needs no external model provider, so the complete
 * run lifecycle can be exercised without model spend or network flakiness.
 *
 * The completion is derived from the conversation so it adapts to any agent:
 * once the latest turns already carry a tool result it answers with final text;
 * otherwise, when the agent exposes tools, it calls the first one with a minimal
 * payload synthesized from that tool's input schema (so the runtime's boundary
 * validation passes); with no tools at all it answers immediately.
 *
 * Enabled by setting `maac.runtime.driver` to `fake` (env `MAAC_LLM_DRIVER`),
 * which {@see RuntimeServiceProvider} binds in place of the
 * production {@see AiLlmRouter}.
 */
class DeterministicLlmRouter implements LlmRouter
{
    /**
     * Produce a deterministic completion for the request.
     */
    public function complete(LlmRequest $request): LlmCompletion
    {
        $usage = new LlmUsage(120, 48);

        if ($request->tools === [] || $this->hasToolResult($request)) {
            return LlmCompletion::text('Deterministic agent response.', $usage);
        }

        $tool = $request->tools[0];

        return LlmCompletion::toolCall($tool->name, $this->sampleArguments($tool->inputSchema), $usage);
    }

    /**
     * Whether the conversation already carries a tool result to answer from.
     */
    private function hasToolResult(LlmRequest $request): bool
    {
        foreach ($request->messages as $message) {
            if ($message->role === 'tool') {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a minimal payload that satisfies the required fields of a schema.
     *
     * @param  array<string, string>  $schema
     * @return array<string, mixed>
     */
    private function sampleArguments(array $schema): array
    {
        $arguments = [];

        foreach ($schema as $field => $definition) {
            if (ToolSchema::isOptional($definition)) {
                continue;
            }

            $arguments[$field] = $this->sampleValue(ToolSchema::baseType($definition));
        }

        return $arguments;
    }

    /**
     * A schema-valid sample value for the given base type.
     */
    private function sampleValue(string $base): mixed
    {
        return match ($base) {
            'number', 'integer' => 1,
            'boolean' => true,
            'object' => ['value' => 'e2e'],
            'array' => ['e2e'],
            default => 'e2e',
        };
    }
}
