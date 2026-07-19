# `maacc/sdk` — MAACC PHP SDK

A framework-agnostic PHP client for the MAACC SDK and runtime API: token
exchange, manifest sync, implementation reporting, and pause/resume agent runs.
Only `ext-curl` and `ext-json` are required.

- **Status:** ✅ Supported · **Version:** 1.0.0 · **MAACC API contract:** v1.0.0
- **Requires:** PHP ≥ 8.2

See the [SDK Integration Guide](../../docs/MAACC_SDK_Integration_Guide.md) for the
full lifecycle, the [Migration Guide](../../docs/MAACC_SDK_Migration_Guide.md) for
versioning policy, the
[Distribution Guide](../../docs/MAACC_SDK_Distribution_Guide.md) for private
package setup, and [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## Install

```bash
composer require maacc/sdk:^1.0
```

Private pilot installs require Composer repository/auth configuration. See the
Distribution Guide before running this command in a consuming application.

## Quick start (simple mode)

```php
use Maacc\Sdk\{MaaccClient, MaaccConfig};
use Maacc\Sdk\Tools\{CallableToolHandler, ToolHandlerRegistry};

$client = new MaaccClient(MaaccConfig::fromEnvironment());

$registry = (new ToolHandlerRegistry)->register(new CallableToolHandler(
    'fetch-records',
    fn (array $args): array => ['records' => MyRepo::search((string) ($args['query'] ?? '')), 'total' => 0],
));

$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('ops-agent', 'Summarize today', $registry);

echo $run->isCompleted() ? $run->response : "Run {$run->status}: {$run->error}";
```

For trusted identity-aware tools, issue and pass a minimized caller context:

```php
$context = $client->issueCallerContext('user:42', department: 'operations', roles: ['viewer']);
$run = $client->run('ops-agent', 'Summarize today', $registry, callerContext: $context);
```

See [`examples/simple.php`](examples/simple.php) and
[`examples/advanced.php`](examples/advanced.php) (version negotiation, pre-flight
validation, manual pause/resume, controlled missing-handler).

## Detect compatibility (Phase 6C)

```php
$compatibility = $client->compatibility();

if (! $compatibility->isCompatible()) {
    // The installed SDK is below MAACC's supported minimum — upgrade before use.
    throw new RuntimeException("SDK requires upgrade to >= {$compatibility->minimumClientVersion}.");
}
```

## Validate a handler before reporting it (Phase 6C)

```php
use Maacc\Sdk\Testing\ToolTester;

$tool = $client->manifest()->tool('fetch-records');
$result = (new ToolTester)->test($tool, $handler, ['query' => 'today']);

if ($result->fails()) {
    // $result->errors lists exactly which input/output schema rules were violated.
}
```

## Receive webhooks safely

Use `WebhookSignature` to verify the raw body and timestamp, or
`WebhookDeliveryVerifier` to verify the stable delivery ID and endpoint sequence
as well. Persist processed delivery IDs with a unique constraint in production;
acknowledge duplicates with 2xx but do not repeat their side effects. Delivery is
at least once and may arrive out of order. See
[`docs/MAACC_Webhook_Delivery_Contract_v1.md`](../../docs/MAACC_Webhook_Delivery_Contract_v1.md).
