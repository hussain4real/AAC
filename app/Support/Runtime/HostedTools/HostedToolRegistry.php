<?php

namespace App\Support\Runtime\HostedTools;

use App\Support\Runtime\Contracts\HostedTool;
use RuntimeException;

/**
 * Resolves the in-platform handler for a MAAC-hosted tool contract by slug.
 * Seeded with the built-in utilities; applications register hosted contracts in
 * MAAC whose slug matches a registered handler.
 */
class HostedToolRegistry
{
    /**
     * The registered hosted tools, keyed by tool contract slug.
     *
     * @var array<string, HostedTool>
     */
    private array $tools = [];

    public function __construct()
    {
        $this->register('echo', new EchoHostedTool);
        $this->register('current_time', new CurrentTimeHostedTool);
    }

    /**
     * Register a hosted tool handler for the given contract slug.
     */
    public function register(string $slug, HostedTool $tool): void
    {
        $this->tools[$slug] = $tool;
    }

    /**
     * Determine whether a handler exists for the given contract slug.
     */
    public function has(string $slug): bool
    {
        return array_key_exists($slug, $this->tools);
    }

    /**
     * Resolve the handler for the given contract slug.
     *
     * @throws RuntimeException when no handler is registered for the slug
     */
    public function resolve(string $slug): HostedTool
    {
        return $this->tools[$slug]
            ?? throw new RuntimeException("No hosted tool handler is registered for [{$slug}].");
    }
}
