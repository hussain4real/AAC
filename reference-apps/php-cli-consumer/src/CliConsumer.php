<?php

declare(strict_types=1);

namespace Maac\Reference\Cli;

use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Resources\Run;
use Maac\Sdk\Tools\ToolHandlerRegistry;

/**
 * Assembles the plain-PHP MAAC integration: an SDK client plus the local tool
 * handler registry, with the two operations the CLI performs (sync local
 * implementations, run the agent).
 */
final class CliConsumer
{
    public function __construct(
        private readonly MaacClient $client,
        private readonly ToolHandlerRegistry $registry,
        private readonly string $agentSlug,
    ) {}

    /**
     * The underlying SDK client.
     */
    public function client(): MaacClient
    {
        return $this->client;
    }

    /**
     * Report every registered handler against the current manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syncImplementations(): array
    {
        return $this->client->reportHandlers($this->client->manifest(), $this->registry, 'php');
    }

    /**
     * Invoke the agent and drive it to a terminal state, servicing client-side
     * tools from the local registry.
     */
    public function run(string $prompt, ?string $caller = 'php-cli-reference'): Run
    {
        return $this->client->run($this->agentSlug, $prompt, $this->registry, $caller);
    }

    /**
     * Build the consumer from the documented MAAC_* environment variables.
     */
    public static function fromEnvironment(?Transport $transport = null): self
    {
        $config = MaacConfig::fromEnvironment();

        $agentSlug = getenv('MAAC_AGENT_SLUG') ?: 'e2e-ops-agent';
        $toolSlug = getenv('MAAC_TOOL_FETCH_RECORDS') ?: 'e2e-fetch-records';

        $registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler($toolSlug));

        return new self(new MaacClient($config, $transport), $registry, $agentSlug);
    }
}
