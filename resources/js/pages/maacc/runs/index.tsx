/* ============================================================
   MAACC — Runs & Audit Logs (list)
   ============================================================ */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { StatCard } from '@/components/maacc/charts';
import { ScopeBanner } from '@/components/maacc/common';
import {
    Btn,
    PageHeader,
    RunBadge,
    Select,
    Table,
    Td,
    Tr,
    inputStyle,
} from '@/components/maacc/ui';
import { formatCurrency } from '@/maacc/format';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';
import { runs as runsRoute } from '@/routes';

export default function Runs() {
    const { go, scope } = useMaaccNav();
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const filters = MAACC.pagination.runs?.filters ?? {};
    const [q, setQ] = useState(String(filters.q ?? ''));
    const [f, setF] = useState({
        application: String(filters.application ?? 'All'),
        status: String(filters.status ?? 'All'),
        agent: String(filters.agent ?? 'All'),
    });

    const all = scope.runs;
    const list = all;
    const pagination = MAACC.pagination.runs;

    const query = () => ({
        ...(q.trim() ? { q: q.trim() } : {}),
        ...(f.application !== 'All' ? { application: f.application } : {}),
        ...(f.agent !== 'All' ? { agent: f.agent } : {}),
        ...(f.status !== 'All' ? { status: f.status } : {}),
        per_page: pagination?.perPage ?? 25,
    });

    const visit = (data: Record<string, string | number>) => {
        if (!currentTeam) {
            return;
        }

        router.get(runsRoute.url(currentTeam.slug), data, {
            only: ['maacc'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visit(query());
    };

    const clearFilters = () => {
        setQ('');
        setF({ application: 'All', status: 'All', agent: 'All' });
        visit({ per_page: pagination?.perPage ?? 25 });
    };

    const visitCursor = (cursor: string | null | undefined) => {
        if (cursor) {
            visit({ ...query(), runs_cursor: cursor });
        }
    };

    const cards = scope.isAll
        ? {
              today: MAACC.dashboard.stats.runsToday.toLocaleString(),
              done: MAACC.dashboard.stats.success.toLocaleString(),
              waiting: MAACC.dashboard.stats.waitingClient,
              failed: MAACC.dashboard.stats.failed,
          }
        : {
              today: all.length.toLocaleString(),
              done: all
                  .filter((r) => r.status === 'completed')
                  .length.toLocaleString(),
              waiting: all.filter((r) => r.status === 'waiting_for_client')
                  .length,
              failed: all.filter((r) =>
                  ['failed', 'expired'].includes(r.status),
              ).length,
          };

    return (
        <>
            <Head title="Runs & Audit Logs" />
            <div className="route-anim">
                <PageHeader
                    title="Runs & Audit Logs"
                    sub="Every agent run is logged with model, tokens, tool calls, latency, and cost — traceable for developers and security reviewers."
                />

                <ScopeBanner scope={scope} />

                <div
                    className="maacc-stat-grid"
                    style={{
                        display: 'grid',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <StatCard
                        label="Runs today"
                        value={cards.today}
                        icon="runs"
                        tone="purple"
                    />
                    <StatCard
                        label="Completed"
                        value={cards.done}
                        icon="checkCircle"
                        tone="teal"
                    />
                    <StatCard
                        label="Waiting for client"
                        value={cards.waiting}
                        icon="clock"
                        tone="orange"
                    />
                    <StatCard
                        label="Failed / expired"
                        value={cards.failed}
                        icon="xCircle"
                        tone="red"
                    />
                </div>

                <form
                    onSubmit={applyFilters}
                    aria-label="Run filters"
                    style={{
                        display: 'flex',
                        gap: 9,
                        marginBottom: 14,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                    }}
                >
                    <div style={{ position: 'relative', width: 260 }}>
                        <Icon
                            name="search"
                            size={15}
                            style={{
                                position: 'absolute',
                                left: 11,
                                top: '50%',
                                transform: 'translateY(-50%)',
                                color: 'var(--text-3)',
                            }}
                        />
                        <input
                            aria-label="Search runs"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search run ID or input…"
                            className="maacc-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        ariaLabel="Filter runs by application"
                        value={f.application}
                        onChange={(v) => setF({ ...f, application: v })}
                        options={[
                            { value: 'All', label: 'All applications' },
                            ...scope.apps.map((a) => ({
                                value: a.id,
                                label: a.name,
                            })),
                        ]}
                        style={{ width: 190 }}
                    />
                    <Select
                        ariaLabel="Filter runs by agent"
                        value={f.agent}
                        onChange={(v) => setF({ ...f, agent: v })}
                        options={[
                            { value: 'All', label: 'All agents' },
                            ...scope.agents.map((a) => ({
                                value: a.id,
                                label: a.name,
                            })),
                        ]}
                        style={{ width: 190 }}
                    />
                    <Select
                        ariaLabel="Filter runs by status"
                        value={f.status}
                        onChange={(v) => setF({ ...f, status: v })}
                        options={[
                            { value: 'All', label: 'All statuses' },
                            { value: 'completed', label: 'Completed' },
                            {
                                value: 'waiting_for_client',
                                label: 'Waiting for client',
                            },
                            { value: 'running', label: 'Running' },
                            { value: 'failed', label: 'Failed' },
                            { value: 'expired', label: 'Expired' },
                            { value: 'cancelled', label: 'Cancelled' },
                        ]}
                        style={{ width: 170 }}
                    />
                    <Btn type="submit" variant="primary" icon="filter">
                        Apply filters
                    </Btn>
                    <Btn type="button" variant="ghost" onClick={clearFilters}>
                        Clear
                    </Btn>
                    <div style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {pagination?.count ?? list.length} runs on this page
                    </span>
                </form>

                <Table
                    columns={[
                        { label: 'Run ID' },
                        { label: 'Agent / App' },
                        { label: 'Caller' },
                        { label: 'Status' },
                        { label: 'Model' },
                        { label: 'Tools', align: 'center' },
                        { label: 'Tokens', align: 'right' },
                        { label: 'Cost', align: 'right' },
                        { label: 'Latency', align: 'right' },
                        { label: 'Started', align: 'right' },
                    ]}
                >
                    {list.map((r) => {
                        const ag = MAACC.agentById(r.agentId);

                        return (
                            <Tr
                                key={r.id}
                                onClick={() => go('run', { id: r.id })}
                            >
                                <Td mono strong>
                                    {r.id}
                                </Td>
                                <Td>
                                    <div>
                                        <div
                                            style={{
                                                color: 'var(--text)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            {ag?.name.replace(' Agent', '')}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11,
                                                color: 'var(--text-3)',
                                            }}
                                        >
                                            {r.appId}
                                        </div>
                                    </div>
                                </Td>
                                <Td mono>{r.caller}</Td>
                                <Td>
                                    <RunBadge status={r.status} dot />
                                </Td>
                                <Td mono style={{ fontSize: 11.5 }}>
                                    {MAACC.llmById(r.llm)?.name}
                                </Td>
                                <Td align="center" mono>
                                    {r.tools.length}
                                </Td>
                                <Td align="right" mono>
                                    {(
                                        r.tokensIn + r.tokensOut
                                    ).toLocaleString()}
                                </Td>
                                <Td align="right" mono>
                                    {formatCurrency(r.cost, r.currency)}
                                </Td>
                                <Td align="right" mono>
                                    {r.latency}
                                </Td>
                                <Td
                                    align="right"
                                    style={{
                                        color: 'var(--text-3)',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {r.started}
                                </Td>
                            </Tr>
                        );
                    })}
                </Table>

                {list.length === 0 && (
                    <div
                        role="status"
                        style={{
                            padding: '32px 16px',
                            textAlign: 'center',
                            color: 'var(--text-3)',
                        }}
                    >
                        No runs match the selected filters.
                    </div>
                )}

                {pagination && (
                    <nav
                        aria-label="Run pages"
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            gap: 12,
                            marginTop: 16,
                        }}
                    >
                        <Btn
                            variant="default"
                            disabled={!pagination.previousCursor}
                            onClick={() =>
                                visitCursor(pagination.previousCursor)
                            }
                        >
                            Previous
                        </Btn>
                        <span
                            aria-live="polite"
                            style={{ fontSize: 12, color: 'var(--text-3)' }}
                        >
                            Showing {pagination.count} of up to{' '}
                            {pagination.perPage} per page
                        </span>
                        <Btn
                            variant="default"
                            disabled={!pagination.nextCursor}
                            onClick={() => visitCursor(pagination.nextCursor)}
                        >
                            Next
                        </Btn>
                    </nav>
                )}

                {MAACC.meta && (
                    <p
                        style={{
                            marginTop: 12,
                            fontSize: 11,
                            color: 'var(--text-3)',
                        }}
                    >
                        Source: {MAACC.meta.source} · Refreshed{' '}
                        {new Date(MAACC.meta.freshAt).toLocaleString()} ·{' '}
                        {MAACC.meta.timezone}
                    </p>
                )}
            </div>
        </>
    );
}
