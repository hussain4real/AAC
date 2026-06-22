import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MaacError } from '../../../packages/maac-sdk-ts/src/index.ts';
import type { HttpRequest, HttpResponse, Transport } from '../../../packages/maac-sdk-ts/src/index.ts';
import { NodeConsumer } from '../src/consumer.ts';

interface ScriptedResponse {
  status: number;
  body: unknown;
}

function fakeTransport(responses: ScriptedResponse[]): { transport: Transport; requests: HttpRequest[] } {
  const requests: HttpRequest[] = [];
  let index = 0;

  const transport: Transport = async (request: HttpRequest): Promise<HttpResponse> => {
    requests.push(request);
    const next = responses[index++];

    if (next === undefined) {
      throw new Error(`No scripted response for ${request.method} ${request.url}`);
    }

    return { status: next.status, body: typeof next.body === 'string' ? next.body : JSON.stringify(next.body) };
  };

  return { transport, requests };
}

function withEnv(values: Record<string, string | undefined>, callback: () => Promise<void>): Promise<void> {
  const previous: Record<string, string | undefined> = {};

  for (const [key, value] of Object.entries(values)) {
    previous[key] = process.env[key];

    if (value === undefined) {
      delete process.env[key];
    } else {
      process.env[key] = value;
    }
  }

  return callback().finally(() => {
    for (const [key, value] of Object.entries(previous)) {
      if (value === undefined) {
        delete process.env[key];
      } else {
        process.env[key] = value;
      }
    }
  });
}

const FULL_ENV = {
  MAAC_BASE_URL: 'https://maac.test',
  MAAC_CLIENT_ID: 'cid',
  MAAC_CLIENT_SECRET: 'secret',
  MAAC_AGENT_SLUG: 'ops',
  MAAC_TOOL_FETCH_RECORDS: 'fetch',
};

test('reports its handler and completes a run from environment configuration', async () => {
  await withEnv(FULL_ENV, async () => {
    const { transport } = fakeTransport([
      { status: 200, body: { access_token: 'tok', expires_in: 3600 } },
      {
        status: 200,
        body: {
          application: { environment: 'production' },
          agents: [{ slug: 'ops', name: 'Ops', version: 'v1', status: 'published', tools: ['fetch'] }],
          tools: [{ name: 'fetch', version: '1.0.0', schema_fingerprint: 'fp', implementation: { status: 'required' } }],
        },
      },
      { status: 200, body: { results: [{ tool: 'fetch', accepted: true, status: 'implemented' }] } },
      {
        status: 201,
        body: {
          run_id: 'run-1',
          agent_slug: 'ops',
          status: 'waiting_for_client',
          usage: { tokens_in: 4, tokens_out: 0 },
          cost: 0.01,
          tool_call: { id: 'call-1', tool: 'fetch', arguments: { query: 'berth' }, output_schema: { records: 'array' } },
        },
      },
      {
        status: 200,
        body: {
          run_id: 'run-1',
          agent_slug: 'ops',
          status: 'completed',
          usage: { tokens_in: 4, tokens_out: 6 },
          cost: 0.02,
          response: 'Operations nominal.',
        },
      },
    ]);

    const consumer = NodeConsumer.fromEnvironment(transport);

    const sync = await consumer.syncImplementations();
    assert.equal(sync[0].status, 'implemented');

    const run = await consumer.run('Summarize current operations');
    assert.equal(run.status, 'completed');
    assert.equal(run.response, 'Operations nominal.');
    assert.equal(run.tokensOut, 6);
  });
});

test('throws a typed error when required environment variables are missing', async () => {
  await withEnv({ ...FULL_ENV, MAAC_CLIENT_SECRET: undefined }, async () => {
    assert.throws(() => NodeConsumer.fromEnvironment(), (error: unknown) => error instanceof MaacError);
  });
});
