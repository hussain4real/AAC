# `@qatar-navigation-milaha/sdk` — MAACC TypeScript SDK

A dependency-free TypeScript client for the MAACC SDK and runtime API: token
exchange, manifest sync, implementation reporting, and pause/resume agent runs.
Zero runtime dependencies — built on the global `fetch`.

- **Status:** ✅ Supported · **Version:** 1.0.0 · **MAACC API contract:** v1.0.0
- **Requires:** Node ≥ 18 (or any runtime with global `fetch`)

See the [SDK Integration Guide](../../docs/MAACC_SDK_Integration_Guide.md) for the
full lifecycle, the [Migration Guide](../../docs/MAACC_SDK_Migration_Guide.md) for
versioning policy, the
[Distribution Guide](../../docs/MAACC_SDK_Distribution_Guide.md) for private
package setup, and [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## Install

```bash
npm install @qatar-navigation-milaha/sdk@^1.0
```

Private pilot installs require GitHub Packages registry/auth configuration. See
the Distribution Guide before running this command in a consuming application.

## Quick start (simple mode)

```ts
import { isCompleted, MaaccClient, ToolHandlerRegistry } from '@qatar-navigation-milaha/sdk';

const client = new MaaccClient({
  baseUrl: process.env.MAACC_BASE_URL!,
  clientId: process.env.MAACC_CLIENT_ID!,
  clientSecret: process.env.MAACC_CLIENT_SECRET!,
});

const registry = new ToolHandlerRegistry().register(
  'fetch-records',
  (args) => ({ records: myRepo.search(String(args.query ?? '')), total: 0 }),
  'fetchRecordsHandler',
);

await client.reportHandlers(await client.manifest(), registry);
const run = await client.run('ops-agent', 'Summarize today', registry);

console.log(isCompleted(run) ? run.response : `Run ${run.status}: ${run.error}`);
```

For trusted identity-aware tools, issue and pass a minimized caller context:

```ts
const context = await client.issueCallerContext('user:42', { department: 'operations', roles: ['viewer'] });
const run = await client.run('ops-agent', 'Summarize today', registry, undefined, 16, context);
```

See [`examples/simple.ts`](examples/simple.ts) and
[`examples/advanced.ts`](examples/advanced.ts) (version negotiation, pre-flight
validation, manual pause/resume, controlled missing-handler).

## Detect compatibility (Phase 6C)

```ts
import { isSdkCompatible } from '@qatar-navigation-milaha/sdk';

const compatibility = await client.compatibility();

if (!isSdkCompatible(compatibility)) {
  // The installed SDK is below MAACC's supported minimum — upgrade before use.
  throw new Error(`SDK requires upgrade to >= ${compatibility.minimumClientVersion}.`);
}
```

## Validate a handler before reporting it (Phase 6C)

```ts
import { findTool, ToolTester } from '@qatar-navigation-milaha/sdk';

const tool = findTool(await client.manifest(), 'fetch-records');
const result = await new ToolTester().test(tool!, handler, { query: 'today' });

if (!result.valid) {
  // result.errors lists exactly which input/output schema rules were violated.
}
```

## Receive webhooks safely

`WebhookDeliveryVerifier` validates the raw-body signature, stable delivery ID,
and endpoint sequence and reports duplicates. Its built-in set is convenient for
one process; production consumers must persist processed IDs with a unique
constraint, acknowledge duplicates with 2xx, and avoid repeating side effects.
Delivery is at least once and can arrive out of order. See
[`docs/MAACC_Webhook_Delivery_Contract_v1.md`](../../docs/MAACC_Webhook_Delivery_Contract_v1.md).
