/* ============================================================
   MAAC — Agents (list)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppMark,
    Badge,
    AgentBadge,
    Btn,
    Card,
    EmptyState,
    PageHeader,
    Select,
    inputStyle,
} from '@/components/maac/ui';
import type { Agent } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ---------- AgentCard (local) ---------- */
function AgentCard({ agent, onOpen }: { agent: Agent; onOpen: () => void }) {
    const MAAC = useMaacData();
    const llm = MAAC.llmById(agent.llm);
    const app = MAAC.appById(agent.appId);
    const clientTools = agent.tools
        .map((t) => MAAC.toolById(t))
        .filter((t) => t?.execMode === 'client');
    const missing = clientTools.filter((t) =>
        ['required', 'outdated', 'incompatible'].includes(t!.impl),
    ).length;

    return (
        <Card
            hover
            onClick={onOpen}
            style={{
                padding: 0,
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <div style={{ padding: '14px 16px 11px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
                        gap: 10,
                    }}
                >
                    <div style={{ display: 'flex', gap: 11, minWidth: 0 }}>
                        <span
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 9,
                                background: 'var(--primary-soft)',
                                color: 'var(--primary)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name="agents" size={19} />
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <div
                                style={{
                                    fontSize: 14.5,
                                    fontWeight: 700,
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                }}
                            >
                                {agent.name}
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    fontSize: 11.5,
                                    color: 'var(--text-3)',
                                    marginTop: 2,
                                }}
                            >
                                <AppMark code={agent.appId} size={14} />
                                {app?.name}{' '}
                                <span
                                    className="mono"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    · {agent.version}
                                </span>
                            </div>
                        </div>
                    </div>
                    <AgentBadge status={agent.status} />
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: 'var(--text-2)',
                        marginTop: 10,
                        lineHeight: 1.5,
                        minHeight: 36,
                    }}
                >
                    {agent.desc}
                </div>
            </div>
            <div
                style={{
                    padding: '0 16px 12px',
                    display: 'flex',
                    gap: 6,
                    flexWrap: 'wrap',
                    alignItems: 'center',
                }}
            >
                <Badge tone="purple" soft icon="llm">
                    {llm?.name}
                </Badge>
                <Badge tone="neutral">{agent.tools.length} tools</Badge>
                {missing > 0 && (
                    <Badge tone="orange" dot>
                        {missing} tool{missing > 1 ? 's' : ''} pending
                    </Badge>
                )}
            </div>
            <div
                style={{
                    marginTop: 'auto',
                    padding: '10px 16px',
                    borderTop: '1px solid var(--border)',
                    background: 'var(--surface-2)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <div
                    className="mono"
                    style={{
                        fontSize: 11,
                        color: 'var(--text-3)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    <Icon name="link" size={12} />
                    /agents/{agent.slug}/runs
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 13,
                        fontSize: 11.5,
                        color: 'var(--text-3)',
                    }}
                >
                    {agent.successRate > 0 && (
                        <span className="maac-tip" data-tip="Success rate">
                            <b
                                className="tnum"
                                style={{
                                    color:
                                        agent.successRate >= 97
                                            ? 'var(--teal-600)'
                                            : 'var(--text)',
                                }}
                            >
                                {agent.successRate}%
                            </b>
                        </span>
                    )}
                    <span>{agent.lastRun}</span>
                </div>
            </div>
        </Card>
    );
}

/* ---------- Page ---------- */
export default function Agents() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const [q, setQ] = useState('');
    const [filters, setFilters] = useState({
        app: 'All',
        status: 'All',
        llm: 'All',
    });

    const list = scope.agents.filter(
        (a) =>
            (filters.app === 'All' || a.appId === filters.app) &&
            (filters.status === 'All' || a.status === filters.status) &&
            (filters.llm === 'All' || a.llm === filters.llm) &&
            a.name.toLowerCase().includes(q.toLowerCase()),
    );

    return (
        <>
            <Head title="Agents" />
            <div className="route-anim">
                <PageHeader
                    title="Agents"
                    sub="AI capabilities configured with a system prompt, model, tools, and runtime settings — exposed through secure API endpoints."
                    actions={
                        <>
                            <Btn
                                variant="default"
                                icon="playground"
                                onClick={() => go('playground')}
                            >
                                Playground
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="plus"
                                onClick={() => go('createAgent')}
                            >
                                Create Agent
                            </Btn>
                        </>
                    }
                />

                <div
                    style={{
                        display: 'flex',
                        gap: 9,
                        marginBottom: 14,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                    }}
                >
                    <div style={{ position: 'relative', width: 240 }}>
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
                            placeholder="Search agents…"
                            className="maac-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        value={filters.app}
                        onChange={(v) => setFilters({ ...filters, app: v })}
                        options={[
                            { value: 'All', label: 'All applications' },
                            ...scope.apps.map((a) => ({
                                value: a.id,
                                label: a.name,
                            })),
                        ]}
                        style={{ width: 200 }}
                    />
                    <Select
                        value={filters.status}
                        onChange={(v) => setFilters({ ...filters, status: v })}
                        options={[
                            { value: 'All', label: 'All statuses' },
                            'Published',
                            'Testing',
                            'Draft',
                            'Disabled',
                        ]}
                        style={{ width: 150 }}
                    />
                    <Select
                        value={filters.llm}
                        onChange={(v) => setFilters({ ...filters, llm: v })}
                        options={[
                            { value: 'All', label: 'All models' },
                            ...MAAC.llms
                                .filter((l) => l.status === 'Approved')
                                .map((l) => ({ value: l.id, label: l.name })),
                        ]}
                        style={{ width: 170 }}
                    />
                    <div style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {list.length} agents
                    </span>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(420px, 1fr))',
                        gap: 12,
                    }}
                >
                    {list.map((a) => (
                        <AgentCard
                            key={a.id}
                            agent={a}
                            onOpen={() => go('agent', { id: a.id })}
                        />
                    ))}
                </div>
                {list.length === 0 && (
                    <Card>
                        <EmptyState
                            icon="agents"
                            title="No agents match"
                            desc="Adjust filters or create a new agent."
                        />
                    </Card>
                )}
            </div>
        </>
    );
}
