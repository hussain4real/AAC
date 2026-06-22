# MAAC Node / TypeScript reference consumer

A reference consumer in a **different language stack** (Node + TypeScript),
proving the MAAC integration contract is not Laravel- or PHP-specific. It uses
the dependency-free [`@maac/sdk`](../../packages/maac-sdk-ts) TypeScript client —
the same token exchange, manifest sync, implementation reporting, and
pause/resume run loop as the PHP consumers.

> Requires Node ≥ 22.18 (runs TypeScript natively via type stripping). For older
> Node, transpile with `tsc` first or run through `tsx`.

## Run

```bash
export MAAC_BASE_URL=https://maac.test
export MAAC_CLIENT_ID=...        # from MAAC → Applications → Credentials
export MAAC_CLIENT_SECRET=...    # shown once on generation/rotation
export MAAC_AGENT_SLUG=e2e-ops-agent
export MAAC_TOOL_FETCH_RECORDS=e2e-fetch-records

node reference-apps/node-consumer/bin/run.ts "Summarize current port operations"
```

If local Herd HTTPS fails with `fetch failed` or
`UNABLE_TO_VERIFY_LEAF_SIGNATURE`, run Node with the system certificate store:

```bash
NODE_OPTIONS=--use-system-ca node reference-apps/node-consumer/bin/run.ts "Summarize current port operations"
```

The client-side `fetch-records` tool is implemented in
[`fetchRecordsHandler.ts`](src/fetchRecordsHandler.ts). When MAAC pauses for it,
the SDK executes it locally, submits the result, and the run completes.

## Test

```bash
npm run test:sdk        # from the MAAC repo root — runs the TS SDK + this consumer's tests
# or directly:
node --test reference-apps/node-consumer/test/*.test.ts
```

See the [MAAC SDK Integration Guide](../../docs/MAAC_SDK_Integration_Guide.md)
for the full contract and troubleshooting.
