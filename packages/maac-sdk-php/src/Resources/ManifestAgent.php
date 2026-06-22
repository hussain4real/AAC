<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * A published agent the application may invoke, plus the slugs of the
 * client-side tools it depends on.
 */
final class ManifestAgent
{
    /**
     * @param  array<int, string>  $tools
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $status,
        public readonly array $tools,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $tools = is_array($data['tools'] ?? null) ? $data['tools'] : [];

        return new self(
            slug: (string) ($data['slug'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            tools: array_values(array_map('strval', $tools)),
        );
    }
}
