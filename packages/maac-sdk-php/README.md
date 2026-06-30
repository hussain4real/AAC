# `maac/sdk` — MAAC PHP SDK

A framework-agnostic PHP client for the MAAC SDK and runtime API: token
exchange, manifest sync, implementation reporting, and pause/resume agent runs.
Only `ext-curl` and `ext-json` are required.

- **Status:** ✅ Supported · **Version:** 0.2.0 · **MAAC API contract:** v0.0.1
- **Requires:** PHP ≥ 8.2

See the [SDK Integration Guide](../../docs/MAAC_SDK_Integration_Guide.md) for the
full lifecycle, the [Migration Guide](../../docs/MAAC_SDK_Migration_Guide.md) for
versioning policy, the
[Distribution Guide](../../docs/MAAC_SDK_Distribution_Guide.md) for private
package setup, and [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## Install

```bash
composer require maac/sdk:^0.2
```

Private pilot installs require Composer repository/auth configuration. See the
Distribution Guide before running this command in a consuming application.

## Quick start (simple mode)

```php
use Maac\Sdk\{MaacClient, MaacConfig};
use Maac\Sdk\Tools\{CallableToolHandler, ToolHandlerRegistry};

$client = new MaacClient(MaacConfig::fromEnvironment());

$registry = (new ToolHandlerRegistry)->register(new CallableToolHandler(
    'fetch-records',
    fn (array $args): array => ['records' => MyRepo::search((string) ($args['query'] ?? '')), 'total' => 0],
));

$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('ops-agent', 'Summarize today', $registry);

echo $run->isCompleted() ? $run->response : "Run {$run->status}: {$run->error}";
```

See [`examples/simple.php`](examples/simple.php) and
[`examples/advanced.php`](examples/advanced.php) (version negotiation, pre-flight
validation, manual pause/resume, controlled missing-handler).

## Detect compatibility (Phase 6C)

```php
$compatibility = $client->compatibility();

if (! $compatibility->isCompatible()) {
    // The installed SDK is below MAAC's supported minimum — upgrade before use.
    throw new RuntimeException("SDK requires upgrade to >= {$compatibility->minimumClientVersion}.");
}
```

## Validate a handler before reporting it (Phase 6C)

```php
use Maac\Sdk\Testing\ToolTester;

$tool = $client->manifest()->tool('fetch-records');
$result = (new ToolTester)->test($tool, $handler, ['query' => 'today']);

if ($result->fails()) {
    // $result->errors lists exactly which input/output schema rules were violated.
}
```
