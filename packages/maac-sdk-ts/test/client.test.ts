import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MaacClient } from '../src/client.ts';
import { MaacApiError, MissingToolHandlerError, TransportError } from '../src/errors.ts';
import { ToolHandlerRegistry } from '../src/registry.ts';
import { fetchTransport } from '../src/transport.ts';
import type { HttpRequest, HttpResponse, Transport } from '../src/transport.ts';

interface ScriptedResponse {
  status: number;
  body: unknown;
}

/**
 * Build a scripted transport that returns queued responses in order and records
 * the requests the SDK made — the TypeScript analogue of MAAC's PHP FakeTransport.
 */
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

const TOKEN: ScriptedResponse = { status: 200, body: { token_type: 'Bearer', expires_in: 3600, access_token: 'tok-123' } };

function client(transport: Transport): MaacClient {
  return new MaacClient({ baseUrl: 'https://maac.test', clientId: 'cid', clientSecret: 'secret' }, transport);
}

test('exchanges the credential for a token via the form grant', async () => {
  const { transport, requests } = fakeTransport([TOKEN]);

  assert.equal(await client(transport).authenticate(), 'tok-123');
  assert.equal(requests[0].method, 'POST');
  assert.equal(requests[0].url, 'https://maac.test/oauth/token');
  assert.equal(requests[0].headers['Content-Type'], 'application/x-www-form-urlencoded');
  assert.match(requests[0].body ?? '', /grant_type=client_credentials/);
});

test('fetch transport surfaces the underlying network failure', async () => {
  const original = globalThis.fetch;
  const cause = Object.assign(new Error('unable to verify the first certificate'), {
    code: 'UNABLE_TO_VERIFY_LEAF_SIGNATURE',
  });

  globalThis.fetch = (() => Promise.reject(new TypeError('fetch failed', { cause }))) as typeof fetch;

  try {
    await assert.rejects(
      () => fetchTransport({ method: 'POST', url: 'https://maac.test/oauth/token', headers: {} }),
      (error: unknown) => {
        assert.ok(error instanceof TransportError);
        assert.match(error.message, /unable to verify the first certificate/);
        assert.match(error.message, /--use-system-ca/);

        return true;
      },
    );
  } finally {
    globalThis.fetch = original;
  }
});

test('fetches and parses the manifest with a bearer token', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    {
      status: 200,
      body: {
        application: { id: 'cargo', name: 'Cargo', environment: 'production' },
        agents: [{ slug: 'ops', name: 'Ops', version: 'v1', status: 'published', tools: ['fetch'] }],
        tools: [
          {
            name: 'fetch',
            version: '1.0.0',
            schema_fingerprint: 'fp-1',
            input_schema: { query: 'string' },
            output_schema: { records: 'array' },
            implementation: { status: 'required' },
          },
        ],
      },
    },
  ]);

  const manifest = await client(transport).manifest();

  assert.equal(manifest.environment, 'production');
  assert.deepEqual(manifest.agents[0].tools, ['fetch']);
  assert.equal(manifest.tools[0].schemaFingerprint, 'fp-1');
  assert.equal(manifest.tools[0].implementation.status, 'required');
  assert.equal(requests[1].headers.Authorization, 'Bearer tok-123');
});

test('reports registered handlers against the manifest', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    {
      status: 200,
      body: {
        application: { environment: 'production' },
        agents: [],
        tools: [{ name: 'fetch', version: '2.1.0', schema_fingerprint: 'fp-9', implementation: { status: 'required' } }],
      },
    },
    { status: 200, body: { results: [{ tool: 'fetch', accepted: true, status: 'implemented' }] } },
  ]);

  const maac = client(transport);
  const registry = new ToolHandlerRegistry().register('fetch', () => ({ records: [], total: 0 }), 'fetchRecordsHandler');

  const results = await maac.reportHandlers(await maac.manifest(), registry, 'typescript');

  assert.equal(results[0].status, 'implemented');
  const reportBody = JSON.parse(requests[2].body ?? '{}');
  assert.deepEqual(reportBody.implementations[0], {
    tool: 'fetch',
    handler_name: 'fetchRecordsHandler',
    version: '2.1.0',
    schema_fingerprint: 'fp-9',
    language: 'typescript',
  });
});

test('drives a paused run to completion through the registry', async () => {
  const { transport, requests } = fakeTransport([
    TOKEN,
    {
      status: 201,
      body: {
        run_id: 'run-1',
        agent_slug: 'ops',
        status: 'waiting_for_client',
        usage: { tokens_in: 5, tokens_out: 0 },
        cost: 0.01,
        tool_call: { id: 'call-1', tool: 'fetch', arguments: { query: 'today' }, output_schema: { records: 'array' } },
      },
    },
    {
      status: 200,
      body: {
        run_id: 'run-1',
        agent_slug: 'ops',
        status: 'completed',
        usage: { tokens_in: 5, tokens_out: 7 },
        cost: 0.03,
        response: 'All clear.',
      },
    },
  ]);

  let captured: Record<string, unknown> = {};
  const registry = new ToolHandlerRegistry().register('fetch', (args) => {
    captured = args;

    return { records: ['a'], total: 1 };
  });

  const run = await client(transport).run('ops', 'Summarize', registry, 'ts-unit');

  assert.equal(run.status, 'completed');
  assert.equal(run.response, 'All clear.');
  assert.equal(run.tokensOut, 7);
  assert.deepEqual(captured, { query: 'today' });

  const submit = JSON.parse(requests[2].body ?? '{}');
  assert.equal(requests[2].url, 'https://maac.test/api/v1/runs/run-1/tool-results');
  assert.equal(submit.tool_call_id, 'call-1');
  assert.deepEqual(submit.result, { records: ['a'], total: 1 });
});

test('throws when MAAC pauses for an unregistered tool', async () => {
  const { transport } = fakeTransport([
    TOKEN,
    {
      status: 201,
      body: {
        run_id: 'run-1',
        agent_slug: 'ops',
        status: 'waiting_for_client',
        usage: { tokens_in: 1, tokens_out: 0 },
        cost: 0,
        tool_call: { id: 'call-1', tool: 'fetch', arguments: {}, output_schema: null },
      },
    },
  ]);

  await assert.rejects(
    () => client(transport).run('ops', 'x', new ToolHandlerRegistry()),
    (error: unknown) => error instanceof MissingToolHandlerError && error.tool === 'fetch',
  );
});

test('returns a non-completed terminal run without throwing', async () => {
  const { transport } = fakeTransport([
    TOKEN,
    {
      status: 201,
      body: {
        run_id: 'run-1',
        agent_slug: 'ops',
        status: 'failed',
        usage: { tokens_in: 1, tokens_out: 0 },
        cost: 0,
        error: 'model not approved',
      },
    },
  ]);

  const run = await client(transport).run('ops', 'x', new ToolHandlerRegistry());

  assert.equal(run.status, 'failed');
  assert.equal(run.error, 'model not approved');
});

test('raises a typed error carrying the MAAC error code and status', async () => {
  const { transport } = fakeTransport([
    TOKEN,
    { status: 403, body: { error: 'credential_revoked', message: 'This credential has been revoked.' } },
  ]);

  await assert.rejects(
    () => client(transport).manifest(),
    (error: unknown) => error instanceof MaacApiError && error.errorCode === 'credential_revoked' && error.status === 403,
  );
});

test('refreshes the token and retries once on a 401', async () => {
  const { transport, requests } = fakeTransport([
    { status: 200, body: { access_token: 'tok-1', expires_in: 3600 } },
    { status: 401, body: { error: 'invalid_token', message: 'expired' } },
    { status: 200, body: { access_token: 'tok-2', expires_in: 3600 } },
    { status: 200, body: { application: { environment: 'production' }, agents: [], tools: [] } },
  ]);

  const manifest = await client(transport).manifest();

  assert.equal(manifest.environment, 'production');
  assert.equal(requests[1].headers.Authorization, 'Bearer tok-1');
  assert.equal(requests[3].headers.Authorization, 'Bearer tok-2');
});
