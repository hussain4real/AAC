<?php

namespace App\Support\Runtime;

use App\Models\ToolContract;

/**
 * The contract metadata the LLM Router exposes to the model so it can decide
 * whether (and how) to request a tool. Derived from a MAAC {@see ToolContract}.
 */
final readonly class LlmToolDefinition
{
    /**
     * @param  array<string, string>  $inputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
    ) {}

    /**
     * Build a tool definition from a MAAC tool contract.
     */
    public static function fromContract(ToolContract $contract): self
    {
        return new self(
            $contract->slug,
            $contract->description ?? '',
            $contract->input_schema,
        );
    }
}
