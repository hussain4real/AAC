#!/usr/bin/env node

/**
 * Standalone Node/TypeScript MAACC test client. Using ONLY the public SDK, it:
 *   1. authenticates with client_credentials,
 *   2. syncs the manifest (the client-side tool starts as "required"),
 *   3. reports its local handler so MAACC REGISTERS the implementation,
 *   4. re-syncs the manifest (the tool now reads "implemented"),
 *   5. runs a published agent — and when the model calls the client-side tool,
 *      MAACC pauses, the app runs its OWN handler locally, submits the result, and
 *      the run resumes. MAACC only ever sees the result, never the app's data.
 *
 * Run against a live MAACC:
 *
 *   MAACC_BASE_URL=https://maacc.test \
 *   MAACC_CLIENT_ID=… MAACC_CLIENT_SECRET=… \
 *   MAACC_AGENT_SLUG=node-port-ops MAACC_TOOL_SLUG=fetch_port_records \
 *   node reference-apps/node-tool-client/bin/run.ts "What's the current port status?"
 */
import { MaaccClient, ToolHandlerRegistry, isCompleted } from '../../../packages/maacc-sdk-ts/src/index.ts';
import type { Manifest } from '../../../packages/maacc-sdk-ts/src/index.ts';
import { portOperationsHandler } from '../src/portOperationsTool.ts';

function env(key: string): string {
  const value = process.env[key];

  if (value === undefined || value === '') {
    throw new Error(`Missing required environment variable: ${key}`);
  }

  return value;
}

function log(message: string): void {
  process.stdout.write(`${message}\n`);
}

function toolStatus(manifest: Manifest, slug: string): string {
  return manifest.tools.find((tool) => tool.name === slug)?.implementation.status ?? '(not in manifest)';
}

const baseUrl = env('MAACC_BASE_URL');
const agentSlug = env('MAACC_AGENT_SLUG');
const toolSlug = env('MAACC_TOOL_SLUG');
const prompt = process.argv[2] ?? 'What is the current port operations status? Flag anything congested or down.';

const client = new MaaccClient({
  baseUrl,
  clientId: env('MAACC_CLIENT_ID'),
  clientSecret: env('MAACC_CLIENT_SECRET'),
});

// Pair the tool slug with the app's LOCAL handler. The wrapper narrates so the
// client-side pause / local-execute / resume is visible in the output.
const registry = new ToolHandlerRegistry().register(
  toolSlug,
  (args, context) => {
    log(`     ⏸  MAACC paused run ${context.run.runId} for client tool "${context.toolCall.tool}" — args ${JSON.stringify(args)}`);
    const result = portOperationsHandler(args, context);
    log(`        ↳ executed locally in the Node app (MAACC never sees this data) → ${JSON.stringify(result)}`);

    return result;
  },
  'portOperationsHandler',
);

try {
  log(`MAACC Node test client → ${baseUrl}\n`);

  log('1) Authenticate (client_credentials)…');
  await client.authenticate();
  log('   ✓ access token acquired\n');

  log('2) Manifest BEFORE reporting the implementation:');
  const before = await client.manifest();
  log(`   tool "${toolSlug}" implementation status = ${toolStatus(before, toolSlug)}\n`);

  log('3) Report the local handler → MAACC registers the implementation…');
  const results = await client.reportHandlers(before, registry, 'typescript');
  log(`   ✓ MAACC accepted: ${JSON.stringify(results)}\n`);

  log('4) Manifest AFTER reporting:');
  const after = await client.manifest();
  log(`   tool "${toolSlug}" implementation status = ${toolStatus(after, toolSlug)}\n`);

  log(`5) Run agent "${agentSlug}" — the SDK services each client-side tool pause from the registry…`);
  log(`   prompt: ${prompt}`);
  const run = await client.run(agentSlug, prompt, registry, 'node-tool-client');

  log(`\n✓ Run ${run.status} — ${run.tokensIn} in / ${run.tokensOut} out, ~$${run.cost.toFixed(4)} est.`);
  log(`\nAgent answer:\n${run.response ?? run.error ?? '(no response)'}`);

  process.exit(isCompleted(run) ? 0 : 1);
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  process.stderr.write(`MAACC integration failed: ${message}\n`);
  process.exit(1);
}
