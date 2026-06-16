/* ============================================================
   MAAC — Runs & Audit Logs (list)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { StatCard } from '@/components/maac/charts';
import { ScopeBanner } from '@/components/maac/common';
import {
    Btn,
    PageHeader,
    RunBadge,
    Select,
    Table,
    Td,
    Tr,
    inputStyle,
} from '@/components/maac/ui';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

export default function Runs() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const [q, setQ] = useState('');
    const [f, setF] = useState({ app: 'All', status: 'All', agent: 'All' });

    const all = scope.runs;
    const list = all.filter(
        (r) =>
            (f.app === 'All' || r.appId === f.app) &&
            (f.status === 'All' || r.status === f.status) &&
            (f.agent === 'All' || r.agentId === f.agent) &&
            (r.id.includes(q.toLowerCase()) ||
                r.input.toLowerCase().includes(q.toLowerCase())),
    );

    const runs7d = scope.agents.reduce((s, a) => s + a.runs7d, 0);
    const runsToday = scope.isAll
        ? MAAC.dashboard.stats.runsToday
        : Math.max(all.length, Math.round(runs7d / 7));
    const cards = scope.isAll
        ? {
              today: MAAC.dashboard.stats.runsToday.toLocaleString(),
              done: MAAC.dashboard.stats.success.toLocaleString(),
              waiting: MAAC.dashboard.stats.waitingClient,
              failed: 40,
          }
        : {
              today: runsToday.toLocaleString(),
              done: Math.round(runsToday * 0.95).toLocaleString(),
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
                    actions={
                        <>
                            <Btn variant="default" icon="filter">
                                Date range
                            </Btn>
                            <Btn variant="default" icon="download">
                                Export logs
                            </Btn>
                        </>
                    }
                />

                <ScopeBanner scope={scope} />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
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

                <div
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
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search run ID or input…"
                            className="maac-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        value={f.app}
                        onChange={(v) => setF({ ...f, app: v })}
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
                    <div style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {list.length} runs
                    </span>
                </div>

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
                        const ag = MAAC.agentById(r.agentId);

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
                                    {MAAC.llmById(r.llm)?.name}
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
                                    ${r.cost.toFixed(4)}
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
            </div>
        </>
    );
}
