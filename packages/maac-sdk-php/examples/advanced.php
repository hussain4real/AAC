<?php

declare(strict_types=1);

/*
 * Advanced mode — explicit control over every step.
 *
 * Demonstrates the Phase 6C features an integration team cares about:
 *   1. version negotiation       — refuse to run an incompatible SDK build;
 *   2. pre-flight validation     — check handlers against the contract before
 *                                  reporting them, with the SDK ToolTester;
 *   3. manual pause/resume        — drive the run loop yourself; and
 *   4. controlled failure         — surface a missing handler instead of hanging.
 *
 * Run with MAAC_BASE_URL / MAAC_CLIENT_ID / MAAC_CLIENT_SECRET set:
 *   php examples/advanced.php
 */

foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;

        break;
    }
}

use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Exceptions\MissingToolHandlerException;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Testing\ToolTester;
use Maac\Sdk\Tools\CallableToolHandler;
use Maac\Sdk\Tools\ToolContext;
use Maac\Sdk\Tools\ToolHandlerRegistry;

$client = new MaacClient(MaacConfig::fromEnvironment());

// 1. Negotiate compatibility before doing anything else.
$compatibility = $client->compatibility();

if (! $compatibility->isCompatible()) {
    fwrite(STDERR, 'Installed SDK v'.MaacClient::VERSION." is {$compatibility->status}; ".
        "MAAC requires >= {$compatibility->minimumClientVersion}. Upgrade the SDK.\n");
    exit(1);
}

foreach ($compatibility->deprecations as $deprecation) {
    $summary = is_string($deprecation['summary'] ?? null) ? $deprecation['summary'] : 'see the migration guide';
    fwrite(STDERR, "⚠️  Deprecation: {$summary}\n");
}

// 2. Fetch the manifest and register the application's local handlers.
$manifest = $client->manifest();
$registry = (new ToolHandlerRegistry)->register(new CallableToolHandler(
    'e2e-fetch-records',
    static fn (array $arguments): array => ['records' => [['id' => 1]], 'total' => 1],
));

// 3. Validate each handler against its contract BEFORE reporting it implemented.
$tester = new ToolTester;

foreach ($registry->registered() as $slug) {
    $tool = $manifest->tool($slug);
    $handler = $registry->resolve($slug);

    if ($tool === null || $handler === null) {
        continue;
    }

    $check = $tester->test($tool, $handler, ['query' => 'today']);

    if ($check->fails()) {
        fwrite(STDERR, "Handler [{$slug}] violates its contract: ".implode('; ', $check->errors)."\n");
        exit(1);
    }
}

$client->reportHandlers($manifest, $registry);

// 4. Drive the run loop manually, servicing each pause and failing loudly on a
//    missing handler rather than letting the run hang.
try {
    $run = $client->startRun('e2e-ops-agent', 'Summarize today');

    while ($run->isWaiting()) {
        $toolCall = $run->toolCall;

        if ($toolCall === null) {
            break;
        }

        $handler = $registry->resolve($toolCall->tool);

        if ($handler === null) {
            throw new MissingToolHandlerException($toolCall->tool);
        }

        $result = $handler->handle($toolCall->arguments, new ToolContext($run, $toolCall));
        $run = $client->submitToolResult($run->runId, $toolCall->id, $result);
    }

    echo $run->isCompleted()
        ? '✅ '.($run->response ?? '').PHP_EOL
        : "⚠️  Run {$run->status}: ".($run->error ?? 'no error').PHP_EOL;
} catch (MissingToolHandlerException $exception) {
    fwrite(STDERR, "No local handler registered for tool [{$exception->tool}]. Register it before invoking.\n");
    exit(1);
} catch (MaacApiException $exception) {
    fwrite(STDERR, "MAAC error [{$exception->errorCode}] (HTTP {$exception->status}): {$exception->getMessage()}\n");
    exit(1);
}
