#!/usr/bin/env node

import { isCompleted } from '../../../packages/maac-sdk-ts/src/index.ts';
import { NodeConsumer } from '../src/consumer.ts';

/**
 * Node/TypeScript CLI entry point. Usage:
 *
 *   MAAC_BASE_URL=https://maac.test \
 *   MAAC_CLIENT_ID=... MAAC_CLIENT_SECRET=... \
 *   MAAC_AGENT_SLUG=e2e-ops-agent \
 *   node reference-apps/node-consumer/bin/run.ts "Summarize port operations"
 */
const prompt = process.argv[2] ?? 'Summarize current port operations.';

try {
  const consumer = NodeConsumer.fromEnvironment();
  await consumer.syncImplementations();
  const run = await consumer.run(prompt);

  process.stdout.write(
    `${JSON.stringify(
      {
        run_id: run.runId,
        status: run.status,
        response: run.response,
        error: run.error,
        usage: { tokens_in: run.tokensIn, tokens_out: run.tokensOut },
        cost: run.cost,
      },
      null,
      2,
    )}\n`,
  );

  process.exit(isCompleted(run) ? 0 : 1);
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  process.stderr.write(`MAAC integration failed: ${message}\n`);
  process.exit(1);
}
