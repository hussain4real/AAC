import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MaaccClient } from '../src/client.ts';
import { MaaccApiError } from '../src/errors.ts';
import { ToolHandlerRegistry } from '../src/registry.ts';
import type { HttpRequest, HttpResponse, Transport } from '../src/transport.ts';

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

const TOKEN: ScriptedResponse = { status: 200, body: { token_type: 'Bearer', expires_in: 3600, access_token: 'tok' } };

function client(transport: Transport): MaaccClient {
  return new MaaccClient({ baseUrl: 'https://maacc.test', clientId: 'cid', clientSecret: 'secret' }, transport);
}

test('starts an async run and reports the mode', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    { status: 202, body: { run_id: 'run-1', agent_slug: 'ops', status: 'queued', usage: { tokens_in: 0, tokens_out: 0 }, cost: 0 } },
  ]);

  const run = await client(transport).startRun('ops', 'go', undefined, 'async');

  assert.equal(run.status, 'queued');
  assert.deepEqual(JSON.parse(requests[1].body ?? '{}'), { input: 'go', mode: 'async' });
});

test('polls an async run until it settles', async () => {
  const status = (s: string): ScriptedResponse => ({
    status: 200,
    body: { run_id: 'run-1', agent_slug: 'ops', status: s, usage: { tokens_in: 1, tokens_out: 1 }, cost: 0.01 },
  });
  const { transport } = fakeTransport([TOKEN, status('queued'), status('running'), status('completed')]);

  const run = await client(transport).pollRun('run-1', 10, 0);

  assert.equal(run.status, 'completed');
});

test('drives an async run through a client tool with polling', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    { status: 202, body: { run_id: 'run-1', agent_slug: 'ops', status: 'queued', usage: { tokens_in: 0, tokens_out: 0 }, cost: 0 } },
    {
      status: 200,
      body: {
        run_id: 'run-1',
        agent_slug: 'ops',
        status: 'waiting_for_client',
        usage: { tokens_in: 5, tokens_out: 0 },
        cost: 0.01,
        tool_call: { id: 'call-1', tool: 'fetch', arguments: { query: 'today' }, output_schema: { records: 'array' } },
      },
    },
    { status: 202, body: { run_id: 'run-1', agent_slug: 'ops', status: 'running', usage: { tokens_in: 5, tokens_out: 0 }, cost: 0.01 } },
    { status: 200, body: { run_id: 'run-1', agent_slug: 'ops', status: 'completed', usage: { tokens_in: 5, tokens_out: 9 }, cost: 0.04, response: 'Done.' } },
  ]);

  const registry = new ToolHandlerRegistry().register('fetch', () => ({ records: ['a'], total: 1 }));
  const run = await client(transport).runAsync('ops', 'Summarize', registry, 'ts-async', { intervalMs: 0 });

  assert.equal(run.status, 'completed');
  assert.equal(run.response, 'Done.');
  assert.deepEqual(JSON.parse(requests[1].body ?? '{}'), { input: 'Summarize', mode: 'async', caller: 'ts-async' });
});

test('registers, lists, and deletes a webhook endpoint', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    { status: 201, body: { id: 'wh-1', url: 'https://app.test/hooks', events: ['run.completed'], environment: 'production', status: 'active', secret: 'whsec_abc' } },
    { status: 200, body: { data: [{ id: 'wh-1', url: 'https://app.test/hooks', events: ['*'], environment: 'production', status: 'active' }] } },
    { status: 204, body: '' },
  ]);

  const maacc = client(transport);

  const endpoint = await maacc.registerWebhook('https://app.test/hooks', ['run.completed']);
  assert.equal(endpoint.secret, 'whsec_abc');
  assert.deepEqual(JSON.parse(requests[1].body ?? '{}'), { url: 'https://app.test/hooks', events: ['run.completed'] });

  const list = await maacc.listWebhooks();
  assert.equal(list.length, 1);
  assert.equal(list[0].secret, null);

  await maacc.deleteWebhook('wh-1');
  assert.equal(requests[3].method, 'DELETE');
});

test('surfaces a controlled error when deleting an unknown endpoint', async () => {
  const { transport } = fakeTransport([
    TOKEN,
    { status: 404, body: { error: 'webhook_endpoint_not_found', message: 'No webhook endpoint matches.' } },
  ]);

  await assert.rejects(
    () => client(transport).deleteWebhook('missing'),
    (error: unknown) => error instanceof MaaccApiError && error.errorCode === 'webhook_endpoint_not_found',
  );
});

test('parses a run stream into events, skipping the termination sentinel', async () => {
  const sse = [
    'event: run.event',
    'data: {"type":"run_requested","sequence":0}',
    '',
    'event: run.state',
    'data: {"status":"completed"}',
    '',
    'event: update',
    'data: </stream>',
    '',
  ].join('\n');

  const { transport } = fakeTransport([TOKEN, { status: 200, body: sse }]);

  const events = await client(transport).streamRun('run-1');

  assert.equal(events.length, 2);
  assert.equal(events[0].event, 'run.event');
  assert.equal(events[0].data.type, 'run_requested');
  assert.equal(events[1].event, 'run.state');
  assert.equal(events[1].data.status, 'completed');
});
