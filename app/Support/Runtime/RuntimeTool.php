<?php

namespace App\Support\Runtime;

use App\Support\Sdk\ToolSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Adapts a MAACC {@see LlmToolDefinition} into a native `laravel/ai` tool so the
 * model can request it through the provider's native function-calling — which
 * reasoning models follow far more reliably than any text protocol. The handler
 * is intentionally never invoked: the runtime caps the SDK at a single step
 * ({@see RuntimeAgent}) so the tool call is handed back to MAACC, which routes it
 * by execution mode (hosted, client, http, …) and can pause for client tools.
 */
class RuntimeTool implements Tool
{
    public function __construct(private readonly LlmToolDefinition $definition) {}

    /**
     * The tool name the model calls, taken from the MAACC tool contract slug.
     */
    public function name(): string
    {
        return $this->definition->name;
    }

    /**
     * The tool's purpose, shown to the model.
     */
    public function description(): Stringable|string
    {
        return $this->definition->description === ''
            ? $this->definition->name
            : $this->definition->description;
    }

    /**
     * Translate the MAACC input-schema DSL into native JSON-schema property types.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->definition->inputSchema as $field => $definition) {
            $type = match (ToolSchema::baseType($definition)) {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                'array' => $schema->array(),
                'object' => $schema->object(),
                default => $schema->string(),
            };

            $properties[$field] = ToolSchema::isOptional($definition) ? $type : $type->required();
        }

        return $properties;
    }

    /**
     * Never called — the runtime intercepts the tool call before execution.
     */
    public function handle(Request $request): Stringable|string
    {
        return '';
    }
}
