<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * A published agent the application may invoke: the slugs of the client-side
 * tools it depends on, plus the server-side tools (hosted/remote HTTP/MCP
 * connector) MAAC executes itself — surfaced so the application can tell them
 * apart without implementing anything for the server-side ones.
 */
final class ManifestAgent
{
    /**
     * @param  array<int, string>  $tools
     * @param  array<int, array{name: string, execution_mode: string, description: string|null}>  $serverTools
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $status,
        public readonly array $tools,
        public readonly array $serverTools = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $tools = is_array($data['tools'] ?? null) ? $data['tools'] : [];
        $serverTools = is_array($data['server_tools'] ?? null) ? $data['server_tools'] : [];

        return new self(
            slug: (string) ($data['slug'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            tools: array_values(array_map('strval', $tools)),
            serverTools: array_values(array_filter(array_map(
                static fn (mixed $tool): ?array => is_array($tool) ? [
                    'name' => (string) ($tool['name'] ?? ''),
                    'execution_mode' => (string) ($tool['execution_mode'] ?? ''),
                    'description' => isset($tool['description']) && is_string($tool['description']) ? $tool['description'] : null,
                ] : null,
                $serverTools,
            ))),
        );
    }
}
