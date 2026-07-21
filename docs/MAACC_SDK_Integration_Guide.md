# MAACC SDK Integration Guide

This guide is for **external application teams** connecting an application to
MAACC. You can follow it without reading any MAACC internals — everything here
uses MAACC's public SDK/runtime APIs.

If you just want working code to copy, see the reference consumers:

- [Laravel consumer](../reference-apps/laravel-consumer) — idiomatic Laravel wiring.
- [Plain-PHP CLI consumer](../reference-apps/php-cli-consumer) — no framework.
- [Node / TypeScript consumer](../reference-apps/node-consumer) — a non-PHP stack.

All three are thin wrappers over a reusable SDK client:

- [`maacc/sdk`](../packages/maacc-sdk-php) — framework-agnostic PHP.
- [`@qatar-navigation-milaha/sdk`](../packages/maacc-sdk-ts) — dependency-free TypeScript.

For private package installation and release steps, see the
[SDK Distribution Guide](MAACC_SDK_Distribution_Guide.md).

## The integration model in one paragraph

MAACC owns the agent, its prompt, its approved model, and the **tool contracts**
(name, input/output JSON schema, version). Your application owns the **handlers**
that implement client-side tools against your own data and permissions. At
runtime MAACC pauses an agent run when the model needs a client-side tool, returns
the tool name and arguments to your app, your handler runs locally, you submit
the result, and MAACC resumes the run. MAACC never reaches into your database.

## Prerequisites (in the MAACC console)

A MAACC operator (Platform Admin / Project Owner) sets this up once:

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
| `MAACC_BASE_URL`           | yes      | `https://maacc.test`      | Base URL of the MAACC instance (no trailing path). |
| `MAACC_CLIENT_ID`          | yes      | `9c3f…`                  | The credential's client id. |
| `MAACC_CLIENT_SECRET`      | yes      | `s3cr3t…`                | Shown once on generate/rotate. |
| `MAACC_AGENT_SLUG`         | yes\*    | `e2e-ops-agent`          | The published agent to invoke. |
| `MAACC_TOOL_FETCH_RECORDS` | yes\*    | `e2e-fetch-records`      | Maps your local handler to a tool contract slug. |
| `MAACC_TIMEOUT`            | no       | `30`                     | Per-request timeout (seconds, PHP SDK). |

\* Required by the reference consumers; the raw SDK takes the agent/tool slugs as
method arguments.

## Token exchange flow

The SDK exchanges the credential for a short-lived bearer token using the OAuth2
**client_credentials** grant (handled for you — shown here for reference):

```
POST {MAACC_BASE_URL}/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={MAACC_CLIENT_ID}&client_secret={MAACC_CLIENT_SECRET}
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
   `schema_fingerprint`. MAACC reconciles each to `implemented`, `outdated`, or
   `incompatible`. The SDK center then shows the tool as implemented.
5. **Issue caller context** — obtain a short-lived signed envelope from
   `POST /api/v1/caller-contexts` when tools need trusted subject, department, or
   role claims. A free-form caller label is informational only.
6. **Invoke the agent** — `POST /api/v1/agents/{agent_slug}/runs` with `input` and
   the optional signed `caller_context`.
   The run either completes, fails, or **pauses** with a `tool_call`.
7. **Service the pause** — when paused (`waiting_for_client`), run the matching
   handler with the supplied `arguments` and submit the result to
   `POST /api/v1/runs/{run_id}/tool-results`.
8. **Repeat** until the run is terminal, then read the final `response`.

The SDK's `run()` method does steps 6–8 for you, given a handler registry.

## Runtime modes (async, polling, streaming, webhooks)

The synchronous `run()` above blocks the HTTP request until the run reaches a
boundary. For long-running or interactive experiences, invoke a run with
`mode: async` — MAACC creates the run, returns `202 queued` immediately, and a
worker drives it. You learn the outcome by **polling**, **streaming**, or a
**webhook**. Every mode records the *same* trace, audit, quota, cost, and
retention data as a synchronous run.

| Mode      | How it works | SDK |
|-----------|--------------|-----|
| Polling   | Start `async`, then read run status until it settles (terminal or paused). | `pollRun()`, or `runAsync()` which also services client-side tools. |
| Streaming | `GET /api/v1/runs/{run_id}/stream` emits the run's trace events as Server-Sent Events, ending with the final state. | `streamRun(runId, onEvent?)`. |
| Webhooks  | Register an endpoint; MAACC `POST`s each lifecycle event (`run.running`, `run.tool_requested`, `run.completed`, `run.failed`, `run.expired`, `run.cancelled`) signed with HMAC-SHA256. | `registerWebhook()`, `listWebhooks()`, `deleteWebhook()`, and `verifyWebhook()` / `WebhookSignature::verify()` on the receiving side. |

```php
// PHP — long-running run, driven by polling (services client tools for you):
$run = $client->runAsync('ops-agent', 'Summarize today', $registry, 'caller', ['intervalMs' => 2000]);

// Register a webhook and verify each delivery on your endpoint:
use Maacc\Sdk\Webhooks\WebhookSignature;
$endpoint = $client->registerWebhook('https://app.example.com/hooks', ['*']); // store $endpoint->secret
$ok = WebhookSignature::verify(
    $request->getContent(),
    $request->header('X-Maacc-Signature'),
    $request->header('X-Maacc-Webhook-Timestamp'),
    $signingSecret,
);

// Stream a run's lifecycle:
$events = $client->streamRun($run->runId, fn ($e) => printf("%s\n", $e->event));
```

```typescript
// TypeScript — equivalents:
const run = await client.runAsync('ops-agent', 'Summarize today', registry, 'caller', { intervalMs: 2000 });

import { verifyWebhook } from '@qatar-navigation-milaha/sdk';
const endpoint = await client.registerWebhook('https://app.example.com/hooks', ['*']);
const ok = verifyWebhook(rawBody, sigHeader, timestampHeader, signingSecret);

const events = await client.streamRun(run.runId, (e) => console.log(e.event, e.data));
```

**Webhook signature.** MAACC signs `"{timestamp}.{body}"` with HMAC-SHA256 and sends
`X-Maacc-Signature: sha256=…` plus `X-Maacc-Webhook-Timestamp`. Verify within a
tolerance window (default 300s) and reject anything that fails — the SDK helpers
do exactly this. Deliveries are retried with exponential backoff and are
observable (and replayable) on the console **Webhooks** page.

## Handler registration pattern

### PHP (`maacc/sdk`)

```php
use Maacc\Sdk\{MaaccClient, MaaccConfig};
use Maacc\Sdk\Tools\{ToolHandler, ToolContext, ToolHandlerRegistry};

final class FetchRecordsHandler implements ToolHandler
{
    public function tool(): string
    {
        return 'fetch-records'; // the tool contract slug
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        // YOUR data access + permissions live here. MAACC only sees the result.
        $records = MyRepository::search((string) ($arguments['query'] ?? ''));

        return ['records' => $records, 'total' => count($records)];
    }
}

$client = new MaaccClient(MaaccConfig::fromEnvironment());
$registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler);

// One call: report what you implement, then run the agent end-to-end.
$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('e2e-ops-agent', 'Summarize today', $registry);

echo $run->isCompleted() ? $run->response : "Run {$run->status}: {$run->error}";
```

### TypeScript (`@qatar-navigation-milaha/sdk`)

```ts
import { MaaccClient, ToolHandlerRegistry, isCompleted } from '@qatar-navigation-milaha/sdk';

const client = new MaaccClient({
  baseUrl: process.env.MAACC_BASE_URL!,
  clientId: process.env.MAACC_CLIENT_ID!,
  clientSecret: process.env.MAACC_CLIENT_SECRET!,
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

See the runnable [PHP examples](../packages/maacc-sdk-php/examples) and
[TypeScript examples](../packages/maacc-sdk-ts/examples) for both simple mode (the
one-call `run()`) and advanced mode (explicit pause/resume, compatibility checks,
pre-flight validation, and controlled missing-handler behaviour).

## Versioning & compatibility

MAACC's SDK surface is versioned. The full policy is in the
[Migration Guide](MAACC_SDK_Migration_Guide.md); the essentials:

- Every response carries an **`X-Maacc-Api-Version`** header, and the manifest
  embeds an **`sdk`** block (`api_version`, the supported client window, packages,
  deprecations).
- **`GET /api/v1/sdk`** negotiates your installed client version. The SDK wraps it
  as `client.compatibility()`, returning `compatible` / `upgrade_required` /
  `ahead` / `unknown`. Check it before invoking anything:

  ```php
  if (! $client->compatibility()->isCompatible()) { /* upgrade the SDK */ }
  ```

- **Validate handlers before reporting them.** The SDK ships a `ToolTester` /
  `validateSchema` that checks a handler's input and output against the contract
  schema — the same rules MAACC enforces — so contract drift is caught in your CI,
  not at runtime:

  ```php
  $result = (new Maacc\Sdk\Testing\ToolTester)->test($tool, $handler, ['query' => 'today']);
  ```

- The **compatibility dashboard** (console → SDK Implementation Center) shows the
  API version, supported client window, reported client versions per application,
  active deprecations, and the **drift feed** of tools whose implementation has
  fallen `outdated`/`incompatible` — so contract changes are visible before they
  are deployed.

## Compatibility matrix

Current **API contract version: v1.0.0**; **SDK packages: v1.0.0**. This coordinated
baseline adds compact-schema 1.0, duplicate-key rejection, signed caller context,
durable status semantics, and scoped manifest behavior. Pre-v1 clients receive
`upgrade_required`. `GET /api/v1/sdk` advertises a `capabilities` block so a client
can detect the supported runtime and tool execution modes before using them.

| SDK / stack                         | Version | Status        | Notes |
|-------------------------------------|---------|---------------|-------|
| PHP SDK (`maacc/sdk`)                | 1.0.0   | ✅ Supported  | PHP ≥ 8.2, ext-curl. Default cURL transport. |
| TypeScript SDK (`@qatar-navigation-milaha/sdk`) | 1.0.0 | ✅ Supported  | Node ≥ 18 (global `fetch`); zero dependencies. |
| Laravel reference consumer          | —       | ✅ Supported  | Service provider + Artisan command. |
| Plain-PHP CLI reference consumer    | —       | ✅ Supported  | No framework. Proves the PHP SDK is framework-agnostic. |
| Node / TypeScript reference consumer| —       | ✅ Supported  | Proves the contract is not Laravel/PHP-only. |
| Python SDK                          | —       | 🧪 Experimental | Stubs are generated by MAACC; a packaged client is coming soon. |
| Async, polling, streaming & webhooks| 0.1.0   | ✅ Supported  | Queue long-running runs; poll, stream (SSE), or receive signed webhooks. |
| Remote HTTP & MCP connector tools   | 1.0.0   | ✅ Supported  | MAACC executes these server-side; the manifest tags them so the app implements nothing. |
| Knowledge retrieval (RAG) tools     | 1.0.0   | ✅ Supported  | MAACC retrieves cited passages from a governed source server-side (`execution_mode` `knowledge`). |
| Read-only database tools            | 1.0.0   | ✅ Supported  | MAACC queries an approved read-only data source server-side under strict policy controls (`execution_mode` `db`). |

Every supported SDK language passes the same shared
[contract fixture suite](../packages/sdk-fixtures), so they decide schema
validity, implementation compatibility, and error handling identically.

## Server-side tools (remote HTTP, MCP connectors, knowledge retrieval & read-only database)

A tool's **execution mode** decides who runs it:

- **Client-side** — your application runs it, through a local handler you
  register and report. These are the only tools that appear in `manifest.tools`
  and `agent.tools` and need a handler.
- **Server-side** — MAACC runs it itself and never calls back to your app:
  - **MAACC-hosted** built-in utilities,
  - **Remote HTTP** tools (MAACC calls an allowlisted external endpoint),
  - **MCP connector** tools (MAACC connects to a registered MCP server as a
    client and invokes one of its tools),
  - **Knowledge retrieval (RAG)** tools (MAACC retrieves cited passages from a
    governed, indexed document source and returns them to the agent), and
  - **Read-only database** tools (MAACC runs a governed, parameterized read-only
    query against an approved data source — a replica or reporting view — under
    statement-type, query-surface, row, and result-size controls, returning only
    the minimized, schema-approved columns; the credential is vault-resolved and
    never stored by MAACC).

You implement **nothing** for server-side tools — you just invoke the agent. The
manifest still surfaces them on each agent as `server_tools`, tagged with their
mode, so you can see what an agent uses end-to-end:

```php
$manifest = $client->manifest();
foreach ($manifest->agents as $agent) {
    foreach ($agent->serverTools as $tool) {
        // $tool['name'], $tool['execution_mode'] (hosted|http|connector|knowledge|db), $tool['description']
        echo "{$agent->slug} runs {$tool['name']} server-side ({$tool['execution_mode']})\n";
    }
}
```

```typescript
const manifest = await client.manifest();
for (const agent of manifest.agents) {
  for (const tool of agent.serverTools) {
    // tool.name, tool.executionMode ('hosted' | 'http' | 'connector' | 'knowledge' | 'db'), tool.description
    console.log(`${agent.slug} runs ${tool.name} server-side (${tool.executionMode})`);
  }
}
```

`GET /api/v1/sdk` (and the manifest's embedded `sdk` block) advertises which
modes are client- vs MAACC-executed:

```jsonc
"capabilities": {
  "tool_execution_modes": {
    "client_side": ["client"],
    "server_side": ["hosted", "http", "connector"]
  }
}
```

Server-side tools follow the **same** schema validation, sensitivity,
governance/approval, quota, trace, and audit standards as client-side tools. A
remote HTTP or MCP tool that targets production can require approval before it
runs (egress, endpoint, and auth are reviewed in the console), and connector and
HTTP failures surface as the same controlled run failures you already handle
(e.g. `connector_unreachable`, `connector_unauthorized`, `remote_http_blocked`).
Registering and mapping these tools is done by platform/project owners in the
MAACC console — no SDK changes are required on your side.

## Error handling

Every controlled failure comes back as a typed exception (`MaaccApiError` in
both SDKs) carrying MAACC's error code and HTTP status:

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

- `MissingToolHandlerError` — MAACC paused for a tool you did not register a
  handler for. Register it (and report it) before invoking.
- `RunNotResolvedError` — the run never reached a terminal state within the loop
  budget (e.g. a handler that keeps producing results MAACC re-pauses on).

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
  prompt is the cause, not the SDK. Inspect the run in the MAACC console (Runs &
  Audit Logs) — the trace shows every model call and tool result.
- **Nothing appears in the MAACC console** — confirm you're using the credential
  for the **same environment** you're inspecting, and that the agent is published.

## Running the validation gate (MAACC maintainers)

The reference apps and SDKs are tested as part of the MAACC suite:

```bash
composer test:reference          # PHP SDK + Laravel + plain-PHP consumer integration + unit tests
npm run test:sdk                 # TypeScript SDK + Node consumer (node:test, zero deps)
npm run types:check:sdk          # tsc for the TS SDK + Node consumer (incl. examples)
php artisan maacc:sdk-fixtures --check   # the shared contract fixtures are up to date (CI tripwire)
composer ci:check                # full gate (includes all of the above)
```

After changing any tool contract schema, compatibility rule, or error envelope,
regenerate the shared fixtures and commit them:

```bash
php artisan maacc:sdk-fixtures
```

For a live, served smoke against the canonical fixture:

```bash
php artisan migrate:fresh --seed
MAACC_LLM_DRIVER=fake php artisan db:seed --class=MaaccE2ESeeder   # prints a one-time credential secret
# then, with that client_id/secret exported as MAACC_CLIENT_ID/MAACC_CLIENT_SECRET and MAACC_BASE_URL:
reference-apps/php-cli-consumer/bin/maacc-run "Summarize today"
NODE_EXTRA_CA_CERTS="$HOME/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem" node reference-apps/node-consumer/bin/run.ts "Summarize today"
```

For Node 22+ consumers, `NODE_OPTIONS=--use-system-ca` is also a valid Herd
HTTPS shortcut. Node 18/20 consumers should use `NODE_EXTRA_CA_CERTS`.
