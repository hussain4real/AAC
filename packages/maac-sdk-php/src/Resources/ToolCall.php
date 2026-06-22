<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * The client-side tool request a paused run is waiting on. The application is
 * expected to execute the named tool with these arguments and submit a result
 * that satisfies {@see self::$outputSchema}.
 */
final class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $outputSchema
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly array $arguments,
        public readonly ?array $outputSchema,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            tool: (string) ($data['tool'] ?? ''),
            arguments: is_array($data['arguments'] ?? null) ? $data['arguments'] : [],
            outputSchema: is_array($data['output_schema'] ?? null) ? $data['output_schema'] : null,
        );
    }
}
