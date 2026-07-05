#!/usr/bin/env node

/**
 * Demonstrates MAACC pushing tool-implementation events to a client. Using ONLY
 * the public SDK, this app:
 *   1. registers a webhook endpoint (for `implementation.reported`) pointing at
 *      its own local HTTP receiver,
 *   2. reports its client-side tool handler — which makes MAACC fire the event,
 *   3. receives the signed webhook, verifies the HMAC-SHA256 signature, prints it.
 *
 * MAACC delivers webhooks from a queue, so a worker must be running:
 *   php artisan queue:work --queue=webhooks
 *
 * Then:
 *   NODE_TLS_REJECT_UNAUTHORIZED=0 MAACC_BASE_URL=https://maacc.test \
 *   MAACC_CLIENT_ID=… MAACC_CLIENT_SECRET=… MAACC_TOOL_SLUG=fetch_port_records \
 *   node reference-apps/node-tool-client/bin/webhook-demo.ts
 */
import http from 'node:http';
import { MaaccClient, ToolHandlerRegistry, verifyWebhook } from '../../../packages/maacc-sdk-ts/src/index.ts';
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

const port = Number(process.env.MAACC_WEBHOOK_PORT ?? '9099');
const toolSlug = env('MAACC_TOOL_SLUG');
const client = new MaaccClient({
  baseUrl: env('MAACC_BASE_URL'),
  clientId: env('MAACC_CLIENT_ID'),
  clientSecret: env('MAACC_CLIENT_SECRET'),
});

try {
  const receiverUrl = `http://localhost:${port}/maacc-webhook`;

  log(`1) Register a webhook endpoint → ${receiverUrl} (events: implementation.reported)…`);
  const endpoint = await client.registerWebhook(receiverUrl, ['implementation.reported'], 'Node client implementation watcher');
  const secret = endpoint.secret ?? '';
  log(`   ✓ endpoint ${endpoint.id} registered; signing secret captured\n`);

  log(`2) Start the local webhook receiver on :${port}…`);
  const received = new Promise<void>((resolve) => {
    const server = http.createServer((request, response) => {
      let body = '';
      request.on('data', (chunk) => {
        body += chunk;
      });
      request.on('end', () => {
        const signature = String(request.headers['x-maacc-signature'] ?? '');
        const timestamp = String(request.headers['x-maacc-webhook-timestamp'] ?? '');
        const valid = secret !== '' && verifyWebhook(body, signature, timestamp, secret);

        response.writeHead(valid ? 200 : 401).end();

        log(`\n📨 Webhook received — event "${request.headers['x-maacc-webhook-event']}", signature ${valid ? 'VALID ✓' : 'INVALID ✗'}:`);
        log(JSON.stringify(JSON.parse(body), null, 2));

        server.close();
        resolve();
      });
    });

    server.listen(port, () => log(`   ✓ receiver listening\n`));
  });

  log('3) Report the client-side tool handler (this makes MAACC fire implementation.reported)…');
  const registry = new ToolHandlerRegistry().register(toolSlug, portOperationsHandler, 'portOperationsHandler');
  const results = await client.reportHandlers(await client.manifest(), registry, 'typescript');
  log(`   ✓ reported: ${JSON.stringify(results)}`);
  log('   …waiting for MAACC to deliver the webhook (needs a running queue worker)…');

  const timeout = new Promise<never>((_, reject) =>
    setTimeout(() => reject(new Error('No webhook within 30s — is `php artisan queue:work --queue=webhooks` running?')), 30_000),
  );
  await Promise.race([received, timeout]);

  if (process.env.MAACC_KEEP_ENDPOINT === '1') {
    log(`\n✓ Done — MAACC pushed the event, the client verified it. Endpoint ${endpoint.id} kept (visible on the console Webhooks page).`);
  } else {
    await client.deleteWebhook(endpoint.id);
    log('\n✓ Done — MAACC pushed the event, the client verified and received it. Endpoint cleaned up.');
  }

  process.exit(0);
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  process.stderr.write(`Webhook demo failed: ${message}\n`);
  process.exit(1);
}
