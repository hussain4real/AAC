<?php

declare(strict_types=1);

namespace Maac\Reference\Laravel;

use Maac\Sdk\MaacClient;
use Maac\Sdk\Resources\Run;
use Maac\Sdk\Tools\ToolHandlerRegistry;

/**
 * The application-facing integration object: it owns a configured
 * {@see MaacClient} and the registry of local tool handlers, exposing the two
 * operations a consuming app actually performs — sync its implementations, and
 * run an agent (servicing client-side tools locally). Everything underneath is
 * the shared SDK; this class adds only the app's wiring.
 */
final class LaravelConsumer
{
    public function __construct(
        private readonly MaacClient $client,
        private readonly ToolHandlerRegistry $registry,
        private readonly string $agentSlug,
    ) {}

    /**
     * The underlying SDK client (useful for reading the manifest or run status).
     */
    public function client(): MaacClient
    {
        return $this->client;
    }

    /**
     * Report every locally-registered handler against the current manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syncImplementations(string $language = 'php'): array
    {
        return $this->client->reportHandlers($this->client->manifest(), $this->registry, $language);
    }

    /**
     * Invoke the configured agent and drive it to completion, executing any
     * client-side tool the run pauses on from the local registry.
     */
    public function summarize(string $prompt, ?string $caller = 'laravel-reference'): Run
    {
        return $this->client->run($this->agentSlug, $prompt, $this->registry, $caller);
    }
}
