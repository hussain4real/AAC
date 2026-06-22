import type { ToolHandler } from '../../../packages/maac-sdk-ts/src/index.ts';

const RECORDS: ReadonlyArray<string> = [
  'Gate 2 — 14 trucks queued',
  'Berth A1 — clear',
  'Berth B3 — loading MV Lusail',
  'Crane 7 — scheduled maintenance',
];

/**
 * The Node app's local implementation of the client-side "fetch records" tool.
 * MAAC never sees this data — only the result, shaped to the contract's output
 * schema (`records`, `total`).
 */
export const fetchRecordsHandler: ToolHandler = (args) => {
  const query = typeof args.query === 'string' ? args.query.toLowerCase() : '';
  const records = RECORDS.filter((record) => query === '' || record.toLowerCase().includes(query));

  return { records, total: records.length };
};
