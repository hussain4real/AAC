# `@maac/sdk` — MAAC TypeScript SDK

A dependency-free TypeScript client for the Milaha AI Agent Center (MAAC) SDK &
runtime API: token exchange, manifest sync, implementation reporting, and
pause/resume agent runs. Zero runtime dependencies — built on the global `fetch`.

- **Status:** ✅ Supported · **Version:** 1.0.0 · **MAAC API contract:** v1.0.0
- **Requires:** Node ≥ 18 (or any runtime with global `fetch`)

See the [SDK Integration Guide](../../docs/MAAC_SDK_Integration_Guide.md) for the
full lifecycle, the [Migration Guide](../../docs/MAAC_SDK_Migration_Guide.md) for
versioning policy, and [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## Install

```bash
npm install @maac/sdk
```

## Quick start (simple mode)

```ts
import { isCompleted, MaacClient, ToolHandlerRegistry } from '@maac/sdk';

const client = new MaacClient({
  baseUrl: process.env.MAAC_BASE_URL!,
  clientId: process.env.MAAC_CLIENT_ID!,
  clientSecret: process.env.MAAC_CLIENT_SECRET!,
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

See [`examples/simple.ts`](examples/simple.ts) and
[`examples/advanced.ts`](examples/advanced.ts) (version negotiation, pre-flight
validation, manual pause/resume, controlled missing-handler).

## Detect compatibility (Phase 6C)

```ts
import { isSdkCompatible } from '@maac/sdk';

const compatibility = await client.compatibility();

if (!isSdkCompatible(compatibility)) {
  // The installed SDK is below MAAC's supported minimum — upgrade before use.
  throw new Error(`SDK requires upgrade to >= ${compatibility.minimumClientVersion}.`);
}
```

## Validate a handler before reporting it (Phase 6C)

```ts
import { findTool, ToolTester } from '@maac/sdk';

const tool = findTool(await client.manifest(), 'fetch-records');
const result = await new ToolTester().test(tool!, handler, { query: 'today' });

if (!result.valid) {
  // result.errors lists exactly which input/output schema rules were violated.
}
```
