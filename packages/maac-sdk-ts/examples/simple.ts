/*
 * Simple mode — the one-call integration.
 *
 * Report the handlers you implement, then let the SDK drive the whole agent run,
 * servicing every client-side tool pause from the registry for you.
 *
 * Run with MAAC_BASE_URL / MAAC_CLIENT_ID / MAAC_CLIENT_SECRET set:
 *   node examples/simple.ts
 */
import { isCompleted, MaacClient, ToolHandlerRegistry } from '../src/index.ts';

const client = new MaacClient({
  baseUrl: process.env.MAAC_BASE_URL ?? '',
  clientId: process.env.MAAC_CLIENT_ID ?? '',
  clientSecret: process.env.MAAC_CLIENT_SECRET ?? '',
});

// Your application's own data access lives in the handler. MAAC only sees the
// returned result, which must satisfy the tool contract's output schema.
const registry = new ToolHandlerRegistry().register(
  'e2e-fetch-records',
  () => ({ records: [{ id: 1 }], total: 1 }),
  'fetchRecordsHandler',
);

// One call each: sync what you implement, then run the agent end-to-end.
await client.reportHandlers(await client.manifest(), registry);
const run = await client.run('e2e-ops-agent', 'Summarize today', registry);

console.log(isCompleted(run) ? `✅ ${run.response}` : `⚠️  Run ${run.status}: ${run.error}`);
