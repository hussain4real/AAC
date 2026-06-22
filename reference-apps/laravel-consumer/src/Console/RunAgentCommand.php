<?php

declare(strict_types=1);

namespace Maac\Reference\Laravel\Console;

use Illuminate\Console\Command;
use Maac\Reference\Laravel\LaravelConsumer;
use Maac\Sdk\Exceptions\MaacException;

/**
 * Demonstrates the end-to-end MAAC integration from the Laravel app's console:
 * sync local tool implementations, invoke the agent, service the client-side
 * tool, and report the final run — using only the public SDK.
 */
final class RunAgentCommand extends Command
{
    protected $signature = 'maac:run-agent {prompt : The prompt to send to the configured MAAC agent}';

    protected $description = 'Invoke the configured MAAC agent, servicing client-side tools from local handlers.';

    public function handle(LaravelConsumer $consumer): int
    {
        $prompt = $this->argument('prompt');
        $prompt = is_string($prompt) ? $prompt : '';

        try {
            $this->info('Syncing tool implementations with MAAC…');
            $consumer->syncImplementations();

            $this->info('Invoking agent…');
            $run = $consumer->summarize($prompt, 'laravel-reference-cli');
        } catch (MaacException $exception) {
            $this->error('MAAC integration failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Run {$run->runId} → {$run->status}");

        if ($run->isCompleted()) {
            $this->line($run->response ?? '');

            return self::SUCCESS;
        }

        $this->error($run->error ?? 'The run did not complete.');

        return self::FAILURE;
    }
}
