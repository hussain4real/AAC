<?php

declare(strict_types=1);

namespace Maacc\Reference\Cli;

use Maacc\Sdk\Contracts\Transport;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Resources\Run;
use Maacc\Sdk\Tools\ToolHandlerRegistry;

/**
 * Assembles the plain-PHP MAACC integration: an SDK client plus the local tool
 * handler registry, with the two operations the CLI performs (sync local
 * implementations, run the agent).
 */
final class CliConsumer
{
    public function __construct(
        private readonly MaaccClient $client,
        private readonly ToolHandlerRegistry $registry,
        private readonly string $agentSlug,
    ) {}

    /**
     * The underlying SDK client.
     */
    public function client(): MaaccClient
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
     * Invoke the agent as a long-running asynchronous run and drive it to
     * completion by polling — the integration mode for a process that cannot
     * hold an HTTP request open while the model works.
     *
     * @param  array{maxIterations?: int, maxAttempts?: int, intervalMs?: int}  $options
     */
    public function runAsync(string $prompt, ?string $caller = 'php-cli-reference', array $options = []): Run
    {
        return $this->client->runAsync($this->agentSlug, $prompt, $this->registry, $caller, $options);
    }

    /**
     * Build the consumer from the documented MAACC_* environment variables.
     */
    public static function fromEnvironment(?Transport $transport = null): self
    {
        $config = MaaccConfig::fromEnvironment();

        $agentSlug = getenv('MAACC_AGENT_SLUG') ?: 'e2e-ops-agent';
        $toolSlug = getenv('MAACC_TOOL_FETCH_RECORDS') ?: 'e2e-fetch-records';

        $registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler($toolSlug));

        return new self(new MaaccClient($config, $transport), $registry, $agentSlug);
    }
}
