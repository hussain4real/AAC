import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { MaacApiError } from '../src/errors.ts';
import type { HttpResponse } from '../src/transport.ts';
import { evaluateCompatibility, validateSchema } from '../src/testing.ts';

/**
 * Proves the TypeScript SDK decides schema validity, implementation
 * compatibility, and error parsing identically to MAAC, by running the shared
 * contract fixture suite (packages/sdk-fixtures) — the same file the PHP SDK and
 * MAAC itself run. If a MAAC rule change regenerates the fixtures, this fails
 * until the SDK is updated to match.
 */
interface ContractFixtures {
  schema_validation: Array<{ name: string; schema: Record<string, unknown>; payload: Record<string, unknown>; valid: boolean; errors: string[] }>;
  compatibility: Array<{ name: string; reported_version: string; current_version: string; reported_fingerprint: string | null; current_fingerprint: string | null; status: string }>;
  errors: Array<{ code: string; status: number }>;
}

const fixtures = JSON.parse(
  readFileSync(new URL('../../sdk-fixtures/contract.json', import.meta.url), 'utf8'),
) as ContractFixtures;

test('decides schema validity exactly like MAAC', () => {
  for (const fixture of fixtures.schema_validation) {
    const result = validateSchema(fixture.schema, fixture.payload);

    assert.equal(result.valid, fixture.valid, fixture.name);
    assert.deepEqual(result.errors, fixture.errors, fixture.name);
  }
});

test('decides implementation compatibility exactly like MAAC', () => {
  for (const fixture of fixtures.compatibility) {
    const status = evaluateCompatibility(
      fixture.reported_version,
      fixture.current_version,
      fixture.reported_fingerprint,
      fixture.current_fingerprint,
    );

    assert.equal(status, fixture.status, fixture.name);
  }
});

test('parses every controlled error envelope', () => {
  for (const fixture of fixtures.errors) {
    const response: HttpResponse = {
      status: fixture.status,
      body: JSON.stringify({ error: fixture.code, message: 'Controlled failure.' }),
    };

    const error = MaacApiError.fromResponse(response);

    assert.equal(error.errorCode, fixture.code, fixture.code);
    assert.equal(error.status, fixture.status, fixture.code);
  }
});
