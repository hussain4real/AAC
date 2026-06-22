import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ToolTester, baseType, isOptional, validateSchema } from '../src/testing.ts';
import type { ManifestTool } from '../src/types.ts';

function manifestTool(input: Record<string, unknown>, output: Record<string, unknown>): ManifestTool {
  return {
    name: 'fetch-records',
    version: '1.0.0',
    schemaFingerprint: 'fp',
    inputSchema: input,
    outputSchema: output,
    implementation: { status: 'required', handlerName: null, implementedVersion: null, lastValidatedAt: null },
  };
}

test('passes a payload that satisfies the schema', () => {
  const result = validateSchema({ query: 'string', limit: 'integer?' }, { query: 'today' });

  assert.equal(result.valid, true);
  assert.deepEqual(result.errors, []);
});

test('reports missing required fields and type mismatches', () => {
  const result = validateSchema({ query: 'string', total: 'number' }, { total: 'not-a-number' });

  assert.equal(result.valid, false);
  assert.deepEqual(result.errors, [
    'Missing required field "query".',
    'Field "total" must be of type number.',
  ]);
});

test('parses optional markers and format hints in definitions', () => {
  assert.equal(baseType('string·date'), 'string');
  assert.equal(baseType('number?'), 'number');
  assert.equal(isOptional('number?'), true);
  assert.equal(isOptional('string'), false);
});

test('validates a handler input and output against the contract', async () => {
  const tool = manifestTool({ query: 'string' }, { records: 'array', total: 'integer' });
  const result = await new ToolTester().test(tool, () => ({ records: ['a', 'b'], total: 2 }), { query: 'today' });

  assert.equal(result.valid, true);
});

test('flags a handler whose result violates the output schema', async () => {
  const tool = manifestTool({ query: 'string' }, { records: 'array', total: 'integer' });
  const result = await new ToolTester().test(tool, () => ({ records: ['a'] }), { query: 123 });

  assert.equal(result.valid, false);
  assert.deepEqual(result.errors, [
    'input: Field "query" must be of type string.',
    'output: Missing required field "total".',
  ]);
});
