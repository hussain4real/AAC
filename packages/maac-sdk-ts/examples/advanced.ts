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
 *   node examples/advanced.ts
 */
import {
  findTool,
  isCompleted,
  isSdkCompatible,
  isWaiting,
  MaacApiError,
  MaacClient,
  MissingToolHandlerError,
  SDK_VERSION,
  ToolHandlerRegistry,
  ToolTester,
} from '../src/index.ts';

const client = new MaacClient({
  baseUrl: process.env.MAAC_BASE_URL ?? '',
  clientId: process.env.MAAC_CLIENT_ID ?? '',
  clientSecret: process.env.MAAC_CLIENT_SECRET ?? '',
});

// 1. Negotiate compatibility before doing anything else.
const compatibility = await client.compatibility();

if (!isSdkCompatible(compatibility)) {
  console.error(
    `Installed SDK v${SDK_VERSION} is ${compatibility.status}; MAAC requires >= ${compatibility.minimumClientVersion}. Upgrade the SDK.`,
  );
  process.exit(1);
}

for (const deprecation of compatibility.deprecations) {
  const summary = typeof deprecation.summary === 'string' ? deprecation.summary : 'see the migration guide';
  console.warn(`⚠️  Deprecation: ${summary}`);
}

// 2. Fetch the manifest and register the application's local handlers.
const manifest = await client.manifest();
const registry = new ToolHandlerRegistry().register(
  'e2e-fetch-records',
  () => ({ records: [{ id: 1 }], total: 1 }),
  'fetchRecordsHandler',
);

// 3. Validate each handler against its contract BEFORE reporting it implemented.
const tester = new ToolTester();

for (const slug of registry.registered()) {
  const tool = findTool(manifest, slug);
  const handler = registry.resolve(slug);

  if (tool === null || handler === null) {
    continue;
  }

  const check = await tester.test(tool, handler, { query: 'today' });

  if (!check.valid) {
    console.error(`Handler [${slug}] violates its contract: ${check.errors.join('; ')}`);
    process.exit(1);
  }
}

await client.reportHandlers(manifest, registry);

// 4. Drive the run loop manually, servicing each pause and failing loudly on a
//    missing handler rather than letting the run hang.
try {
  let run = await client.startRun('e2e-ops-agent', 'Summarize today');

  while (isWaiting(run)) {
    const toolCall = run.toolCall;

    if (toolCall === null) {
      break;
    }

    const handler = registry.resolve(toolCall.tool);

    if (handler === null) {
      throw new MissingToolHandlerError(toolCall.tool);
    }

    const result = await handler(toolCall.arguments, { run, toolCall });
    run = await client.submitToolResult(run.runId, toolCall.id, result);
  }

  console.log(isCompleted(run) ? `✅ ${run.response}` : `⚠️  Run ${run.status}: ${run.error}`);
} catch (error) {
  if (error instanceof MissingToolHandlerError) {
    console.error(`No local handler registered for tool [${error.tool}]. Register it before invoking.`);
    process.exit(1);
  }

  if (error instanceof MaacApiError) {
    console.error(`MAAC error [${error.errorCode}] (HTTP ${error.status}): ${error.message}`);
    process.exit(1);
  }

  throw error;
}
