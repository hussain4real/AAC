/* ============================================================
   MAAC — Agent Detail
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { VBars, StatCard, Progress } from '@/components/maac/charts';
import {
    AppHistory,
    NoAccess,
    PlaceholderScreen,
} from '@/components/maac/common';
import {
    AgentBadge,
    Avatar,
    Badge,
    Btn,
    Card,
    CodeBlock,
    EnvBadge,
    ExecChip,
    ImplBadge,
    KV,
    PageHeader,
    RunBadge,
    SectionHeader,
    SensBadge,
    Table,
    Tabs,
    Td,
    Toggle,
    Tr,
    scopeBadge,
    TOOL_TYPE_META,
} from '@/components/maac/ui';
import type { KVItem } from '@/components/maac/ui';
import type { Agent, Llm, Project, Application, Tool, Run } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { RouteName } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ---------- SafetyToggle (local) ---------- */
function SafetyToggle({ initial }: { initial: boolean }) {
    const [on, setOn] = useState(initial);

    return <Toggle on={on} onChange={setOn} />;
}

/* ---------- AgentOverview (local) ---------- */
function AgentOverview({
    agent,
    llm,
    project,
    app,
    runs,
    go,
    setTab,
}: {
    agent: Agent;
    llm: Llm | undefined;
    project: Project | undefined;
    app: Application | undefined;
    runs: Run[];
    go: (name: RouteName, params?: Record<string, string | undefined>) => void;
    setTab: (tab: string) => void;
}) {
    const MAAC = useMaacData();
    const spark = [62, 70, 66, 78, 74, 88, 84, 96, 90, 104, 98, 112];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 12,
                    }}
                >
                    <StatCard
                        label="Success rate"
                        value={
                            agent.successRate ? agent.successRate + '%' : '—'
                        }
                        icon="checkCircle"
                        tone="teal"
                    />
                    <StatCard
                        label="Runs (7d)"
                        value={agent.runs7d.toLocaleString()}
                        icon="runs"
                        tone="purple"
                    />
                    <StatCard
                        label="Avg latency"
                        value="4.3s"
                        icon="clock"
                        tone="blue"
                    />
                    <StatCard
                        label="Tools"
                        value={agent.tools.length}
                        icon="tools"
                        tone="amber"
                    />
                </div>
                <Card>
                    <SectionHeader title="Configuration" icon="settings" />
                    <KV
                        cols={3}
                        items={
                            [
                                {
                                    k: 'Application',
                                    v: (
                                        <span
                                            className="maac-link"
                                            onClick={() =>
                                                go('application', {
                                                    id: app!.id,
                                                })
                                            }
                                        >
                                            {app?.name}
                                        </span>
                                    ),
                                },
                                { k: 'Project', v: project?.name },
                                {
                                    k: 'Environment',
                                    v: <EnvBadge env={project?.env ?? ''} />,
                                },
                                {
                                    k: 'Model',
                                    v: (
                                        <Badge tone="purple" soft icon="llm">
                                            {llm?.name}
                                        </Badge>
                                    ),
                                },
                                { k: 'Version', v: agent.version, mono: true },
                                { k: 'Temperature', v: agent.temp, mono: true },
                                {
                                    k: 'Max tokens',
                                    v: agent.maxTokens,
                                    mono: true,
                                },
                                { k: 'Slug', v: agent.slug, mono: true },
                                {
                                    k: 'Status',
                                    v: <AgentBadge status={agent.status} />,
                                },
                            ] satisfies KVItem[]
                        }
                    />
                </Card>
                <Card pad={false}>
                    <div style={{ padding: '14px 16px 12px' }}>
                        <SectionHeader
                            title="Recent runs"
                            icon="runs"
                            style={{ marginBottom: 0 }}
                            right={
                                <Btn
                                    variant="ghost"
                                    size="sm"
                                    iconRight="arrowRight"
                                    onClick={() => setTab('runs')}
                                >
                                    All
                                </Btn>
                            }
                        />
                    </div>
                    <Table
                        columns={[
                            { label: 'Run' },
                            { label: 'Status' },
                            { label: 'Tools' },
                            { label: 'Latency', align: 'right' },
                            { label: 'Started', align: 'right' },
                        ]}
                    >
                        {runs.slice(0, 5).map((r) => (
                            <Tr
                                key={r.id}
                                onClick={() => go('run', { id: r.id })}
                            >
                                <Td mono strong>
                                    {r.id}
                                </Td>
                                <Td>
                                    <RunBadge status={r.status} dot />
                                </Td>
                                <Td mono>{r.tools.length}</Td>
                                <Td align="right" mono>
                                    {r.latency}
                                </Td>
                                <Td
                                    align="right"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    {r.started}
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                </Card>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="API endpoint" icon="link" />
                    <div
                        className="mono"
                        style={{
                            fontSize: 11.5,
                            padding: '9px 11px',
                            background: 'var(--code-bg)',
                            border: '1px solid var(--border)',
                            borderRadius: 'var(--r-sm)',
                            color: 'var(--code-text)',
                            wordBreak: 'break-all',
                            lineHeight: 1.6,
                        }}
                    >
                        <span
                            style={{
                                color: 'var(--teal-600)',
                                fontWeight: 600,
                            }}
                        >
                            POST
                        </span>{' '}
                        /api/maac/agents/
                        <span style={{ color: 'var(--primary)' }}>
                            {agent.slug}
                        </span>
                        /runs
                    </div>
                    <Btn
                        variant="soft"
                        size="sm"
                        full
                        style={{ marginTop: 10 }}
                        iconRight="arrowRight"
                        onClick={() => setTab('api')}
                    >
                        View request & response
                    </Btn>
                </Card>
                <Card>
                    <SectionHeader
                        title="Run trend"
                        sub="Last 12h"
                        icon="runs"
                    />
                    <VBars
                        data={spark.map((v) => ({ label: '', value: v }))}
                        height={90}
                        labels={false}
                        color="var(--primary)"
                    />
                </Card>
                <Card>
                    <SectionHeader title="Tool readiness" icon="link" />
                    {(() => {
                        const cs = agent.tools
                            .map((t) => MAAC.toolById(t))
                            .filter(
                                (t): t is Tool =>
                                    t !== undefined && t.execMode === 'client',
                            );
                        const done = cs.filter(
                            (t) => t.impl === 'implemented',
                        ).length;

                        return (
                            <>
                                <Progress
                                    value={done}
                                    max={cs.length || 1}
                                    color={
                                        done === cs.length
                                            ? 'var(--teal-500)'
                                            : 'var(--orange-600)'
                                    }
                                    showVal
                                />
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: 'var(--text-3)',
                                        marginTop: 7,
                                    }}
                                >
                                    {done} of {cs.length} client-side tools
                                    implemented
                                    {done < cs.length &&
                                        ' — agent not production-ready'}
                                </div>
                            </>
                        );
                    })()}
                </Card>
            </div>
        </div>
    );
}

/* ---------- AgentPrompt (local) ---------- */
function AgentPrompt({ agent }: { agent: Agent }) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 300px',
                gap: 14,
            }}
        >
            <Card>
                <SectionHeader
                    title="System prompt"
                    sub="Defines the agent's role, boundaries, and expected behavior"
                    icon="doc"
                    right={
                        <Btn variant="default" size="sm" icon="edit">
                            Edit
                        </Btn>
                    }
                />
                <div
                    style={{
                        fontFamily: 'var(--mono)',
                        fontSize: 12.5,
                        lineHeight: 1.7,
                        color: 'var(--text)',
                        padding: '14px 16px',
                        background: 'var(--code-bg)',
                        border: '1px solid var(--border)',
                        borderRadius: 'var(--r-md)',
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {agent.prompt}
                </div>
            </Card>
            <Card>
                <SectionHeader title="Prompt guidance" icon="info" />
                <ul
                    style={{
                        margin: 0,
                        paddingLeft: 18,
                        fontSize: 12.5,
                        color: 'var(--text-2)',
                        lineHeight: 1.7,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                    }}
                >
                    <li>
                        State the agent's role and the application it serves.
                    </li>
                    <li>Require all claims to be grounded in tool results.</li>
                    <li>Define explicit boundaries and refusal conditions.</li>
                    <li>Specify the output format the application expects.</li>
                </ul>
                <div
                    style={{
                        marginTop: 14,
                        padding: '11px 13px',
                        background: 'var(--primary-soft)',
                        borderRadius: 'var(--r-md)',
                        fontSize: 12,
                        color: 'var(--text-2)',
                        lineHeight: 1.5,
                    }}
                >
                    <b style={{ color: 'var(--primary)' }}>
                        {agent.prompt.length}
                    </b>{' '}
                    characters · ~{Math.round(agent.prompt.length / 4)} tokens
                </div>
            </Card>
        </div>
    );
}

/* ---------- AgentTools (local) ---------- */
function AgentTools({
    agent,
    go,
}: {
    agent: Agent;
    go: (name: RouteName, params?: Record<string, string | undefined>) => void;
}) {
    const MAAC = useMaacData();
    const tools = agent.tools
        .map((t) => MAAC.toolById(t))
        .filter((t): t is Tool => t !== undefined);

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    gap: 10,
                    padding: '12px 14px',
                    background: 'var(--primary-soft)',
                    borderRadius: 'var(--r-md)',
                    border: '1px solid var(--primary-soft-2)',
                    marginBottom: 14,
                }}
            >
                <Icon
                    name="info"
                    size={18}
                    style={{
                        color: 'var(--primary)',
                        flexShrink: 0,
                        marginTop: 1,
                    }}
                />
                <div
                    style={{
                        fontSize: 12.5,
                        color: 'var(--text-2)',
                        lineHeight: 1.5,
                    }}
                >
                    This agent uses <b>{tools.length} tools</b> across multiple
                    execution modes.{' '}
                    <b style={{ color: 'var(--orange-600)' }}>
                        Client-side tools
                    </b>{' '}
                    must be implemented inside the owning application before the
                    agent can run in that environment.
                </div>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {tools.map((t) => (
                    <Card
                        key={t.id}
                        hover
                        onClick={() => go('tool', { id: t.id })}
                        style={{
                            padding: '13px 15px',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 14,
                        }}
                    >
                        <span
                            style={{
                                width: 38,
                                height: 38,
                                borderRadius: 9,
                                background: `var(--${TOOL_TYPE_META[t.execMode]?.tone || 'purple'}-100, var(--primary-soft))`,
                                color: `var(--${TOOL_TYPE_META[t.execMode]?.tone === 'orange' ? 'orange-600' : TOOL_TYPE_META[t.execMode]?.tone === 'teal' ? 'teal-600' : TOOL_TYPE_META[t.execMode]?.tone === 'blue' ? 'blue-500' : 'primary'})`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon
                                name={
                                    t.execMode === 'client'
                                        ? 'link'
                                        : t.execMode === 'knowledge'
                                          ? 'book'
                                          : t.execMode === 'http'
                                            ? 'globe'
                                            : 'tools'
                                }
                                size={18}
                            />
                        </span>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <span
                                    className="mono"
                                    style={{ fontSize: 13.5, fontWeight: 600 }}
                                >
                                    {t.name}
                                </span>
                                {scopeBadge(t.scope)}
                                <ExecChip mode={t.execMode} />
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-3)',
                                    marginTop: 3,
                                }}
                            >
                                {t.desc}
                            </div>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'flex-end',
                                gap: 6,
                                flexShrink: 0,
                            }}
                        >
                            {t.execMode === 'client' ? (
                                <ImplBadge status={t.impl} />
                            ) : (
                                <Badge tone="teal" dot>
                                    Ready
                                </Badge>
                            )}
                            <SensBadge level={t.sensitivity} />
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}

/* ---------- AgentAPI (local) ---------- */
function AgentAPI({ agent }: { agent: Agent }) {
    const MAAC = useMaacData();
    const llm = MAAC.llmById(agent.llm);
    const reqBody = `{
  "input": "Summarize today's operations and flag any delays over 6 hours.",
  "context": {
    "user_id": "u_8821",
    "department": "Maritime & Logistics",
    "application": "${agent.appId}"
  }
}`;
    const toolReq = `{
  "status": "requires_tool",
  "run_id": "run_8fa31c",
  "tool_call": {
    "id": "tc_4a91",
    "name": "getOperationalRecords",
    "arguments": {
      "from_date": "2026-06-08",
      "to_date": "2026-06-08",
      "status": "active"
    }
  }
}`;
    const resBody = `{
  "status": "completed",
  "run_id": "run_8fa31c",
  "output": "12 vessels active. 2 voyages delayed >6h: ...",
  "usage": { "input_tokens": 3120, "output_tokens": 840 },
  "model": "${llm?.code}",
  "tool_calls": 2,
  "latency_ms": 4180
}`;

    return (
        <div
            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Endpoint" icon="link" />
                    <div
                        className="mono"
                        style={{
                            fontSize: 13,
                            padding: '11px 13px',
                            background: 'var(--code-bg)',
                            border: '1px solid var(--border)',
                            borderRadius: 'var(--r-sm)',
                            color: 'var(--code-text)',
                            marginBottom: 14,
                        }}
                    >
                        <Badge tone="teal" soft style={{ marginRight: 8 }}>
                            POST
                        </Badge>
                        /api/maac/agents/{agent.slug}/runs
                    </div>
                    <SectionHeader
                        title="Headers"
                        icon="key"
                        style={{ marginBottom: 8 }}
                    />
                    <CodeBlock
                        code={`Authorization: Bearer <client_token>\nContent-Type: application/json\nX-MAAC-Project: prj_${agent.appId.toLowerCase()}`}
                    />
                </Card>
                <Card pad={false}>
                    <div
                        style={{
                            padding: '12px 14px',
                            borderBottom: '1px solid var(--border)',
                            fontSize: 12.5,
                            fontWeight: 700,
                        }}
                    >
                        Request body
                    </div>
                    <CodeBlock
                        code={reqBody}
                        lang="json"
                        style={{ border: 'none', borderRadius: 0 }}
                    />
                </Card>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card pad={false} style={{ borderColor: 'var(--orange-400)' }}>
                    <div
                        style={{
                            padding: '12px 14px',
                            borderBottom: '1px solid var(--border)',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                        }}
                    >
                        <Icon
                            name="clock"
                            size={15}
                            style={{ color: 'var(--orange-600)' }}
                        />
                        <span style={{ fontSize: 12.5, fontWeight: 700 }}>
                            Intermediate: tool request
                        </span>
                        <Badge tone="orange" style={{ marginLeft: 'auto' }}>
                            pauses run
                        </Badge>
                    </div>
                    <CodeBlock
                        code={toolReq}
                        lang="json"
                        style={{ border: 'none', borderRadius: 0 }}
                    />
                    <div
                        style={{
                            padding: '10px 14px',
                            fontSize: 11.5,
                            color: 'var(--text-3)',
                            borderTop: '1px solid var(--border)',
                            background: 'var(--surface-2)',
                        }}
                    >
                        The application's SDK runs the matching local handler,
                        then POSTs the result to{' '}
                        <span className="mono">
                            /agent-runs/run_8fa31c/tool-results
                        </span>
                        .
                    </div>
                </Card>
                <Card pad={false} style={{ borderColor: 'var(--teal-300)' }}>
                    <div
                        style={{
                            padding: '12px 14px',
                            borderBottom: '1px solid var(--border)',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                        }}
                    >
                        <Icon
                            name="check2"
                            size={15}
                            style={{ color: 'var(--teal-600)' }}
                        />
                        <span style={{ fontSize: 12.5, fontWeight: 700 }}>
                            Final response
                        </span>
                    </div>
                    <CodeBlock
                        code={resBody}
                        lang="json"
                        style={{ border: 'none', borderRadius: 0 }}
                    />
                </Card>
            </div>
        </div>
    );
}

/* ---------- AgentVersions (local) ---------- */
function AgentVersions({ agent }: { agent: Agent }) {
    const cur = parseInt(agent.version.replace('v', '')) || 1;
    const versions = Array.from({ length: cur }, (_, i) => {
        const v = cur - i;

        return {
            v: 'v' + v,
            current: v === cur,
            date:
                ([
                    '2 days ago',
                    '1 week ago',
                    '3 weeks ago',
                    '1 month ago',
                    '2 months ago',
                ][i] as string | undefined) ?? 'earlier',
            note:
                ([
                    'Tuned prompt for delay threshold; added notifyWorkflowOwner.',
                    'Switched to GPT-4o; raised max tokens.',
                    'Added searchPolicyDocuments tool.',
                    'Adjusted temperature to 0.3.',
                    'Initial draft.',
                ][i] as string | undefined) ?? 'Update',
            author:
                (['r.saleh', 'k.almansoori', 'r.saleh', 'r.saleh', 'r.saleh'][
                    i
                ] as string | undefined) ?? 'r.saleh',
        };
    });

    return (
        <Card pad={false}>
            <div style={{ padding: '14px 16px' }}>
                <SectionHeader
                    title="Version history"
                    icon="layers"
                    style={{ marginBottom: 0 }}
                />
            </div>
            <div style={{ padding: '0 16px 8px' }}>
                {versions.map((v, i) => (
                    <div
                        key={v.v}
                        style={{
                            display: 'flex',
                            gap: 14,
                            padding: '12px 0',
                            borderTop: i ? '1px solid var(--border)' : 'none',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <span
                                style={{
                                    width: 30,
                                    height: 30,
                                    borderRadius: 999,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 11,
                                    fontWeight: 700,
                                    fontFamily: 'var(--mono)',
                                    background: v.current
                                        ? 'var(--primary)'
                                        : 'var(--surface-3)',
                                    color: v.current
                                        ? 'var(--primary-contrast)'
                                        : 'var(--text-2)',
                                }}
                            >
                                {v.v}
                            </span>
                            {i < versions.length - 1 && (
                                <span
                                    style={{
                                        flex: 1,
                                        width: 2,
                                        background: 'var(--border)',
                                        marginTop: 4,
                                    }}
                                />
                            )}
                        </div>
                        <div style={{ flex: 1, paddingBottom: 4 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                }}
                            >
                                <span style={{ fontSize: 13, fontWeight: 600 }}>
                                    {v.v}
                                </span>
                                {v.current && (
                                    <Badge tone="teal">Current</Badge>
                                )}
                                <span
                                    style={{
                                        fontSize: 11.5,
                                        color: 'var(--text-3)',
                                        marginLeft: 'auto',
                                    }}
                                >
                                    {v.date}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-2)',
                                    marginTop: 3,
                                }}
                            >
                                {v.note}
                            </div>
                            <div
                                style={{
                                    fontSize: 11,
                                    color: 'var(--text-3)',
                                    marginTop: 4,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                }}
                            >
                                <Avatar name={v.author} size={16} />
                                {v.author}
                                {!v.current && (
                                    <button
                                        className="maac-link"
                                        style={{
                                            border: 'none',
                                            background: 'none',
                                            color: 'var(--primary)',
                                            fontWeight: 600,
                                            cursor: 'pointer',
                                            marginLeft: 8,
                                        }}
                                    >
                                        Restore
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}

/* ---------- AgentSafety (local) ---------- */
function AgentSafety({ agent, llm }: { agent: Agent; llm: Llm | undefined }) {
    const MAAC = useMaacData();
    const settings = [
        {
            label: 'Prompt guardrails',
            on: true,
            desc: 'Screen incoming prompts for policy violations and injection attempts.',
        },
        {
            label: 'Tool-call guardrails',
            on: true,
            desc: 'Validate tool arguments against schema and policy before dispatch.',
        },
        {
            label: 'Tool result validation',
            on: true,
            desc: 'Validate client tool results against the output schema before reasoning.',
        },
        {
            label: 'Mask sensitive results in logs',
            on: agent.tools.some((t) =>
                ['Restricted', 'Confidential'].includes(
                    MAAC.toolById(t)?.sensitivity ?? '',
                ),
            ),
            desc: 'Restricted & Confidential tool outputs are masked in run logs.',
        },
        {
            label: 'Require approval before production',
            on: agent.status !== 'Published',
            desc: 'Agent publication to Production requires owner approval.',
        },
        {
            label: 'Human review for low confidence',
            on: false,
            desc: 'Flag low-confidence responses for human review before returning.',
        },
    ];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <Card>
                <SectionHeader title="Safety & guardrails" icon="shield" />
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                    {settings.map((s, i) => (
                        <div
                            key={i}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 14,
                                padding: '13px 0',
                                borderTop: i
                                    ? '1px solid var(--border)'
                                    : 'none',
                            }}
                        >
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 13, fontWeight: 600 }}>
                                    {s.label}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-3)',
                                        marginTop: 2,
                                    }}
                                >
                                    {s.desc}
                                </div>
                            </div>
                            <SafetyToggle initial={s.on} />
                        </div>
                    ))}
                </div>
            </Card>
            <Card>
                <SectionHeader title="Runtime limits" icon="bolt" />
                <KV
                    cols={1}
                    items={
                        [
                            { k: 'Timeout (sync call)', v: '30s', mono: true },
                            { k: 'Pending tool timeout', v: '60s', mono: true },
                            { k: 'Max tool calls / run', v: '8', mono: true },
                            { k: 'Max payload size', v: '256 KB', mono: true },
                            {
                                k: 'Data residency',
                                v:
                                    llm?.id === 'llama3-70b'
                                        ? 'On-prem (Doha)'
                                        : 'Approved cloud',
                            },
                        ] satisfies KVItem[]
                    }
                />
            </Card>
        </div>
    );
}

/* ---------- Page ---------- */
export default function Show({ id }: { id: string }) {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const agent = MAAC.agentById(id);
    const [tab, setTab] = useState('overview');

    if (!agent) {
        return <PlaceholderScreen name="Agent" />;
    }

    if (!scope.has.agent(id)) {
        return <NoAccess kind="agent" />;
    }

    const llm = MAAC.llmById(agent.llm);
    const project = MAAC.projectById(agent.projectId);
    const app = MAAC.appById(agent.appId);
    const agentRuns = MAAC.runs.filter((r) => r.agentId === id);

    const tabs = [
        { id: 'overview', label: 'Overview', icon: 'dashboard' },
        { id: 'prompt', label: 'System Prompt', icon: 'doc' },
        {
            id: 'tools',
            label: 'Tools',
            icon: 'tools',
            count: agent.tools.length,
        },
        { id: 'api', label: 'API Endpoint', icon: 'code' },
        {
            id: 'runs',
            label: 'Recent Runs',
            icon: 'runs',
            count: agentRuns.length,
        },
        { id: 'versions', label: 'Versions', icon: 'layers' },
        { id: 'safety', label: 'Safety', icon: 'shield' },
    ];

    return (
        <>
            <Head title={agent ? agent.name : 'Agent'} />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        { label: 'Agents', onClick: () => go('agents') },
                        { label: agent.name },
                    ]}
                    title={agent.name}
                    badge={
                        <>
                            <AgentBadge status={agent.status} />
                            <Badge tone="neutral" className="mono">
                                {agent.version}
                            </Badge>
                        </>
                    }
                    sub={agent.desc}
                    actions={
                        <>
                            <Btn variant="default" icon="copy">
                                Clone
                            </Btn>
                            <Btn
                                variant="default"
                                icon="playground"
                                onClick={() =>
                                    go('playground', { agent: agent.id })
                                }
                            >
                                Test in Playground
                            </Btn>
                            {agent.status === 'Published' ? (
                                <Btn variant="danger" icon="power">
                                    Disable
                                </Btn>
                            ) : (
                                <Btn variant="primary" icon="check2">
                                    Publish
                                </Btn>
                            )}
                        </>
                    }
                    tabs={<Tabs tabs={tabs} active={tab} onChange={setTab} />}
                />
                {tab === 'overview' && (
                    <AgentOverview
                        agent={agent}
                        llm={llm}
                        project={project}
                        app={app}
                        runs={agentRuns}
                        go={go}
                        setTab={setTab}
                    />
                )}
                {tab === 'prompt' && <AgentPrompt agent={agent} />}
                {tab === 'tools' && <AgentTools agent={agent} go={go} />}
                {tab === 'api' && <AgentAPI agent={agent} />}
                {tab === 'runs' && <AppHistory app={{ id: agent.appId }} />}
                {tab === 'versions' && <AgentVersions agent={agent} />}
                {tab === 'safety' && <AgentSafety agent={agent} llm={llm} />}
            </div>
        </>
    );
}
