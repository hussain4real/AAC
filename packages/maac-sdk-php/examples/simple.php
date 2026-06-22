<?php

declare(strict_types=1);

/*
 * Simple mode — the one-call integration.
 *
 * Report the handlers you implement, then let the SDK drive the whole agent run,
 * servicing every client-side tool pause from the registry for you.
 *
 * Run with MAAC_BASE_URL / MAAC_CLIENT_ID / MAAC_CLIENT_SECRET set:
 *   php examples/simple.php
 */

foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;

        break;
    }
}

use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\CallableToolHandler;
use Maac\Sdk\Tools\ToolHandlerRegistry;

$client = new MaacClient(MaacConfig::fromEnvironment());

// Your application's own data access lives in the handler. MAAC only sees the
// returned result, which must satisfy the tool contract's output schema.
$registry = (new ToolHandlerRegistry)->register(new CallableToolHandler(
    'e2e-fetch-records',
    static fn (array $arguments): array => ['records' => [['id' => 1]], 'total' => 1],
));

// One call each: sync what you implement, then run the agent end-to-end.
$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('e2e-ops-agent', 'Summarize today', $registry);

echo $run->isCompleted()
    ? '✅ '.($run->response ?? '').PHP_EOL
    : "⚠️  Run {$run->status}: ".($run->error ?? 'no error').PHP_EOL;
