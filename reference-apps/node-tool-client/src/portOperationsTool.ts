import type { ToolHandler } from '../../../packages/maacc-sdk-ts/src/index.ts';

interface PortRecord {
  area: string;
  status: string;
  detail: string;
}

/**
 * The Node app's OWN operational data — it lives only here, inside the consuming
 * application. MAACC never sees it: the model can only obtain it by asking the app
 * to run the client-side `fetch_port_records` tool, and MAACC receives just the
 * returned result, shaped to the tool contract's output schema.
 */
const RECORDS: ReadonlyArray<PortRecord> = [
  { area: 'Gate 2', status: 'congested', detail: '14 trucks queued (~40 min dwell)' },
  { area: 'Gate 5', status: 'clear', detail: 'no queue' },
  { area: 'Berth A1', status: 'available', detail: 'open for assignment' },
  { area: 'Berth B3', status: 'occupied', detail: 'MV Lusail loading containers' },
  { area: 'Crane 7', status: 'down', detail: 'scheduled maintenance until 18:00 AST' },
];

/**
 * The local implementation of the client-side tool. Filters the app's records by
 * an optional query and returns them in the contract's `{ records, total }` shape.
 */
export const portOperationsHandler: ToolHandler = (args) => {
  const query = typeof args.query === 'string' ? args.query.toLowerCase() : '';
  const terms = query.split(/[^a-z0-9]+/).filter((term) => term.length >= 3);

  const matched = RECORDS.filter((record) => {
    const text = `${record.area} ${record.status} ${record.detail}`.toLowerCase();

    return terms.length === 0 || terms.some((term) => text.includes(term));
  });

  // A broad/unmatched query returns the full current snapshot.
  const records = matched.length > 0 ? matched : RECORDS;

  return {
    records: records.map((record) => `${record.area} — ${record.status}: ${record.detail}`),
    total: records.length,
  };
};
