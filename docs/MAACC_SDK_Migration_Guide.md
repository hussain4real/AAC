# MAACC SDK Versioning & Migration Guide

This guide explains how MAACC versions its integration contract, how an
application detects whether its SDK and tool implementations are compatible, and
how to migrate safely when a contract changes. It complements the
[SDK Integration Guide](MAACC_SDK_Integration_Guide.md), which covers the
day-to-day lifecycle.

## What is versioned

MAACC's SDK surface is a **versioned product** with three independently meaningful
versions:

| Version                     | What it describes                                          | Where you see it |
|-----------------------------|------------------------------------------------------------|------------------|
| **API contract version**    | The shape of the `/api/v1/*` envelopes + the manifest.     | `X-Maacc-Api-Version` header on every response; `api_version` in the manifest and at `GET /api/v1/sdk`. |
| **SDK package version**     | The installed client library (`maacc/sdk`, `@qatar-navigation-milaha/sdk`). | `MaaccClient::VERSION` / `SDK_VERSION`; reported as `X-Maacc-Sdk-Version` and `sdk_version`. |
| **Tool contract version**   | A single tool's input/output schema + semantic version.    | `version` + `schema_fingerprint` per tool in the manifest. |

All three follow [Semantic Versioning](https://semver.org/). An SDK package's
MAJOR tracks the API contract MAJOR it targets: **API contract v1.x ⇒ SDK v1.x**.

## Migrating from pre-v1 to v1

API 1.0.0 and SDK 1.0.0 are the enterprise contract baseline frozen by [ADR-P2-001](MAACC_Public_API_v1_Contract_ADR.md). Pre-v1 clients receive `upgrade_required`.

1. Upgrade to `maacc/sdk:^1.0` or `@qatar-navigation-milaha/sdk@^1.0`.
2. Re-fetch the manifest and validate every local handler against `schema_dialect = https://maacc.dev/schema/compact/1.0`.
3. Issue a short-lived caller context and pass it to `startRun`, `run`, or `runAsync`; do not make authorization decisions from the free-form caller label.
4. Treat `waiting_for_client` as the durable pause and service its `tool_call` once. Do not wait for a persisted `requires_tool` status.
5. Reject duplicate JSON keys before signing or sending custom requests. The official SDK serializers already produce compliant JSON.
6. Re-report handlers with SDK 1.0.0 and confirm the manifest shows `implemented` for every `required` tool.

### v1 run-status migration

`requires_tool` now names an internal model decision only. The server materializes the tool-call record and atomically exposes `waiting_for_client` to consumers. Both SDKs continue to parse the legacy token throughout 1.x, but helpers such as `isWaiting()` intentionally return true only for `waiting_for_client`. Compatibility parsing is scheduled for removal in 2.0.0, no earlier than 18 January 2027.

### v1 schema migration

Legacy scalar definitions (`string`, `integer?`, `string·date`) remain accepted during 1.x. New and changed contracts should use rich compact definitions for nested `properties`, array `items`, enums, formats, and bounds. Objects are closed by default; set `additionalProperties: true` only after data-minimization review. Legacy scalar definitions are scheduled for removal in 2.0.0, no earlier than 18 January 2027.

## Detecting compatibility

### Is my SDK compatible with this MAACC?

Call `GET /api/v1/sdk` (the SDK does this for you):

```php
$compatibility = $client->compatibility();      // PHP
```
```ts
const compatibility = await client.compatibility(); // TypeScript
```

It returns a verdict for the version your client reports:

| `status`            | `compatible` | Meaning |
|---------------------|--------------|---------|
| `compatible`        | ✅           | Within the supported window — safe to use. |
| `ahead`             | ✅           | Newer than this MAACC's `current_client_version`; still served. |
| `upgrade_required`  | ❌           | Below `minimum_client_version` — **upgrade before relying on it.** |
| `unknown`           | ✅           | No version reported (not an SDK client). |

The response also carries `minimum_client_version`, `current_client_version`,
the published `packages`, and any active `deprecations`.

### Are my tool implementations compatible?

Each manifest tool carries the current `version` and `schema_fingerprint`. After
you report a handler (`POST /api/v1/tool-implementations`), MAACC reconciles it to
one of:

| Status         | Cause | Fix |
|----------------|-------|-----|
| `implemented`  | Your reported version + fingerprint match the contract. | — |
| `outdated`     | You reported an **older** contract version. | Re-fetch the manifest, update, re-report the current `version`. |
| `incompatible` | Your reported `schema_fingerprint` **differs** — the schema shape changed. | Update your handler to the new input/output shape, then re-report. |

You can predict this locally before reporting, with the SDK test helpers (see
below), so a contract change never surprises you at runtime.

## Seeing changes before deployment — the compatibility dashboard

The MAACC console's **SDK Implementation Center → SDK Versioning & Compatibility**
panel surfaces, per environment:

- the **API contract version** and the **supported client window**;
- the published **SDK packages** and their support tier;
- **active deprecations** and their removal windows; and
- the **contract drift feed** — every application/tool pair whose reported
  implementation has fallen `outdated` or `incompatible`.

The drift feed is the "look before you deploy" view: it lists exactly which
integrations must migrate before a contract change is rolled out to an
environment.

## Deprecation windows

When a contract change needs a transition period, MAACC records a deprecation
(in `config/maacc.php` → `sdk.deprecations`) with a `deprecated_in` version, a
`removed_in` version, and a `guide` anchor pointing here. Deprecations are
surfaced:

- in the `GET /api/v1/sdk` response and the manifest's `sdk` block (so SDKs can
  warn at runtime — both SDK examples print active deprecations); and
- on the compatibility dashboard.

A deprecated behaviour keeps working until its `removed_in` version. Migrate any
time within the window.

## Migrating a tool contract (for MAACC operators)

1. **Change the contract** (schema and/or version) in the console. The
   `schema_fingerprint` changes automatically when the shape changes.
2. Watch the **drift feed**: applications still on the old shape show as
   `incompatible`; applications on an older version show as `outdated`.
3. **Notify the owning application teams.** They update their handler, validate
   it locally (below), and re-report.
4. When the drift feed is clear for the target environment, the change is safe to
   rely on there.

## Validating a handler before reporting it (for application teams)

Use the SDK test helpers to check a handler against the contract **in your own
CI**, before reporting it as implemented:

```php
use Maacc\Sdk\Testing\ToolTester;

$tool = $client->manifest()->tool('fetch-records');
$result = (new ToolTester)->test($tool, $handler, ['query' => 'today']);
// $result->passes() — input + output satisfy the contract schema.
```
```ts
import { findTool, ToolTester } from '@qatar-navigation-milaha/sdk';

const tool = findTool(await client.manifest(), 'fetch-records');
const result = await new ToolTester().test(tool!, handler, { query: 'today' });
// result.valid — input + output satisfy the contract schema.
```

## Upgrading the SDK package

1. Bump the dependency to the target major (`composer require maacc/sdk:^1.0` / `npm i @qatar-navigation-milaha/sdk@^1.0` for API v1).
2. Read this CHANGELOG entry for the new MAJOR and follow any per-change steps.
3. Run `client.compatibility()` against each target MAACC environment — it must
   return `compatible` (or `ahead`).
4. Re-run your handler validation (above) against the fresh manifest.

## For SDK maintainers — the contract fixture tripwire

Every supported SDK language must pass the shared contract fixtures in
[`packages/sdk-fixtures`](../packages/sdk-fixtures), generated from MAACC's own
logic. The workflow:

```bash
php artisan maacc:sdk-fixtures          # regenerate after any contract change
php artisan maacc:sdk-fixtures --check   # CI tripwire (wired into composer ci:check)
```

If a server-side rule or response shape changes, the fixtures change; the
`--check` fails until they are committed; and then each SDK's fixture test fails
until that SDK is updated to match. This is what prevents a MAACC change from
silently breaking a supported client.
