# MAAC SDK Integration Guide

This guide is for **external application teams** connecting an application to the
Milaha AI Agent Center (MAAC). You can follow it without reading any MAAC
internals — everything here uses MAAC's public SDK/runtime APIs.

If you just want working code to copy, see the reference consumers:

- [Laravel consumer](../reference-apps/laravel-consumer) — idiomatic Laravel wiring.
- [Plain-PHP CLI consumer](../reference-apps/php-cli-consumer) — no framework.
- [Node / TypeScript consumer](../reference-apps/node-consumer) — a non-PHP stack.

All three are thin wrappers over a reusable SDK client:

- [`milaha/maac-sdk`](../packages/maac-sdk-php) — framework-agnostic PHP.
- [`@maac/sdk`](../packages/maac-sdk-ts) — dependency-free TypeScript.

## The integration model in one paragraph

MAAC owns the agent, its prompt, its approved model, and the **tool contracts**
(name, input/output JSON schema, version). Your application owns the **handlers**
that implement client-side tools against your own data and permissions. At
runtime MAAC pauses an agent run when the model needs a client-side tool, returns
the tool name and arguments to your app, your handler runs locally, you submit
the result, and MAAC resumes the run. MAAC never reaches into your database.

## Prerequisites (in the MAAC console)

A MAAC operator (Platform Admin / Project Owner) sets this up once:

1. **Register your application** (Applications → Register) and note its environment.
2. **Generate a credential** (Applications → *app* → Credentials → Generate). The
   **client secret is shown only once** — copy it immediately. Store it like any
   other secret. You can rotate it later (rotation re-displays a new secret).
3. Make sure a **published agent** exists in a project under your application,
   wired to an **approved model** for your environment and to your **client-side
   tool contract(s)**.

You now have a `client_id` and `client_secret` scoped to one application +
environment.

## Environment variables

The SDKs and reference apps read these variables:

| Variable                  | Required | Example                  | Notes |
|---------------------------|----------|--------------------------|-------|
| `MAAC_BASE_URL`           | yes      | `https://maac.test`      | Base URL of the MAAC instance (no trailing path). |
| `MAAC_CLIENT_ID`          | yes      | `9c3f…`                  | The credential's client id. |
| `MAAC_CLIENT_SECRET`      | yes      | `s3cr3t…`                | Shown once on generate/rotate. |
| `MAAC_AGENT_SLUG`         | yes\*    | `e2e-ops-agent`          | The published agent to invoke. |
| `MAAC_TOOL_FETCH_RECORDS` | yes\*    | `e2e-fetch-records`      | Maps your local handler to a tool contract slug. |
| `MAAC_TIMEOUT`            | no       | `30`                     | Per-request timeout (seconds, PHP SDK). |

\* Required by the reference consumers; the raw SDK takes the agent/tool slugs as
method arguments.

## Token exchange flow

The SDK exchanges the credential for a short-lived bearer token using the OAuth2
**client_credentials** grant (handled for you — shown here for reference):

```
POST {MAAC_BASE_URL}/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={MAAC_CLIENT_ID}&client_secret={MAAC_CLIENT_SECRET}
```

Response: `{ "token_type": "Bearer", "expires_in": 3600, "access_token": "…" }`.
The SDK caches the token, refreshes it before expiry, and retries once on a 401.
Every other call sends `Authorization: Bearer {access_token}`.

## The lifecycle

1. **Authenticate** — exchange the credential for a token.
2. **Fetch the manifest** — `GET /api/v1/manifest` lists the agents you may invoke
   and the client-side tools you must implement, with their schemas, versions,
   `schema_fingerprint`, and current implementation status.
3. **Implement handlers** — write a local handler per client-side tool that
   returns data satisfying the tool's output schema.
4. **Report implementations** — `POST /api/v1/tool-implementations` with the
   tool, your handler name, the contract version, and the manifest's
   `schema_fingerprint`. MAAC reconciles each to `implemented`, `outdated`, or
   `incompatible`. The SDK center then shows the tool as implemented.
5. **Invoke the agent** — `POST /api/v1/agents/{agent_slug}/runs` with `input`.
   The run either completes, fails, or **pauses** with a `tool_call`.
6. **Service the pause** — when paused (`waiting_for_client`), run the matching
   handler with the supplied `arguments` and submit the result to
   `POST /api/v1/runs/{run_id}/tool-results`.
7. **Repeat** until the run is terminal, then read the final `response`.

The SDK's `run()` method does steps 5–7 for you, given a handler registry.

## Handler registration pattern

### PHP (`milaha/maac-sdk`)

```php
use Maac\Sdk\{MaacClient, MaacConfig};
use Maac\Sdk\Tools\{ToolHandler, ToolContext, ToolHandlerRegistry};

final class FetchRecordsHandler implements ToolHandler
{
    public function tool(): string
    {
        return 'fetch-records'; // the tool contract slug
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        // YOUR data access + permissions live here. MAAC only sees the result.
        $records = MyRepository::search((string) ($arguments['query'] ?? ''));

        return ['records' => $records, 'total' => count($records)];
    }
}

$client = new MaacClient(MaacConfig::fromEnvironment());
$registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler);

// One call: report what you implement, then run the agent end-to-end.
$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('e2e-ops-agent', 'Summarize today', $registry);

echo $run->isCompleted() ? $run->response : "Run {$run->status}: {$run->error}";
```

### TypeScript (`@maac/sdk`)

```ts
import { MaacClient, ToolHandlerRegistry, isCompleted } from '@maac/sdk';

const client = new MaacClient({
  baseUrl: process.env.MAAC_BASE_URL!,
  clientId: process.env.MAAC_CLIENT_ID!,
  clientSecret: process.env.MAAC_CLIENT_SECRET!,
});

const registry = new ToolHandlerRegistry().register(
  'fetch-records',
  (args) => {
    const records = myRepository.search(String(args.query ?? ''));
    return { records, total: records.length };
  },
  'fetchRecordsHandler',
);

await client.reportHandlers(await client.manifest(), registry);
const run = await client.run('e2e-ops-agent', 'Summarize today', registry);

console.log(isCompleted(run) ? run.response : `Run ${run.status}: ${run.error}`);
```

## Compatibility matrix

| SDK / stack                         | Status        | Notes |
|-------------------------------------|---------------|-------|
| PHP SDK (`milaha/maac-sdk`)         | ✅ Supported  | PHP ≥ 8.2, ext-curl. Default cURL transport. |
| TypeScript SDK (`@maac/sdk`)        | ✅ Supported  | Node ≥ 18 (global `fetch`); zero dependencies. |
| Laravel reference consumer          | ✅ Supported  | Service provider + Artisan command. |
| Plain-PHP CLI reference consumer    | ✅ Supported  | No framework. Proves the PHP SDK is framework-agnostic. |
| Node / TypeScript reference consumer| ✅ Supported  | Proves the contract is not Laravel/PHP-only. |
| Python SDK                          | 🧪 Experimental | Stubs are generated by MAAC; a packaged client is planned. |
| Async / webhook / streaming runtime | 🗓️ Planned    | Phase 6D. Today runs are synchronous request/response. |
| Remote HTTP & MCP connector tools   | 🗓️ Planned    | Phase 6E. Today only client-side + MAAC-hosted tools execute. |

## Error handling

Every controlled failure comes back as a typed exception (`MaacApiError` in
both SDKs) carrying MAAC's error code and HTTP status:

| Code                   | HTTP | Meaning / fix |
|------------------------|------|---------------|
| `invalid_token`        | 401  | Token missing/expired. The SDK refreshes and retries automatically. |
| `unknown_client`       | 401  | Token not tied to a registered application. Check the credential. |
| `credential_revoked`   | 403  | The credential was revoked. Generate a new one. |
| `agent_not_found`      | 404  | No such agent **for your application** (tenant isolation). Check the slug + that the agent belongs to your app. |
| `agent_not_published`  | 409  | The agent is a draft. Publish it in the console. |
| `run_not_found`        | 404  | Wrong run id, or it belongs to another application. |
| `run_not_waiting`      | 409  | Submitting a tool result to a run that is not paused. |
| `payload_too_large`    | 413  | Tool result exceeds the contract's `max_payload_kb`. The run stays resumable. |
| `quota_exceeded`       | 429  | A per-period run/token quota was reached. Back off / raise the quota. |
| `invalid_tool_result`  | 422  | Your result failed the output schema. `error.errors` lists the problems. |

Two SDK-side exceptions surface integration mistakes early:

- `MissingToolHandlerError` — MAAC paused for a tool you did not register a
  handler for. Register it (and report it) before invoking.
- `RunNotResolvedError` — the run never reached a terminal state within the loop
  budget (e.g. a handler that keeps producing results MAAC re-pauses on).

## Troubleshooting

- **`tool_not_found` when reporting** — the tool slug doesn't exist for your
  application, or it isn't a client-side tool. Re-check the manifest's `tools[].name`.
- **Tool stuck on `incompatible`** — your reported `schema_fingerprint` doesn't
  match the contract. Always report the fingerprint **from the current manifest**
  (the SDK's `reportHandlers` does this). It means the contract schema changed —
  update your handler to the new input/output shape.
- **Tool stuck on `outdated`** — you reported an older contract version than the
  current one. Re-fetch the manifest and report the current version.
- **`invalid_tool_result`** — your handler's return value doesn't match the
  output schema (missing key or wrong base type). Inspect `output_schema` on the
  `tool_call`.
- **Run completes but the response is empty / wrong** — the agent's model or
  prompt is the cause, not the SDK. Inspect the run in the MAAC console (Runs &
  Audit Logs) — the trace shows every model call and tool result.
- **Nothing appears in the MAAC console** — confirm you're using the credential
  for the **same environment** you're inspecting, and that the agent is published.

## Running the validation gate (MAAC maintainers)

The reference apps and SDKs are tested as part of the MAAC suite:

```bash
composer test:reference   # PHP SDK + Laravel + plain-PHP consumer integration + unit tests
npm run test:sdk          # TypeScript SDK + Node consumer (node:test, zero deps)
npm run types:check:sdk   # tsc for the TS SDK + Node consumer
composer ci:check         # full gate (includes the two npm steps above)
```

For a live, served smoke against the canonical fixture:

```bash
php artisan migrate:fresh --seed
MAAC_LLM_DRIVER=fake php artisan db:seed --class=MaacE2ESeeder   # prints a one-time credential secret
# then, with that client_id/secret exported as MAAC_CLIENT_ID/MAAC_CLIENT_SECRET and MAAC_BASE_URL:
reference-apps/php-cli-consumer/bin/maac-run "Summarize today"
NODE_OPTIONS=--use-system-ca node reference-apps/node-consumer/bin/run.ts "Summarize today"
```
