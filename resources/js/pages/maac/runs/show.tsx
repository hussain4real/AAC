/* ============================================================
   MAAC — Run Trace (single run detail + execution timeline)
   ============================================================ */
import { Head } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { NoAccess } from '@/components/maac/common';
import {
    Badge,
    Btn,
    Card,
    KV,
    PageHeader,
    RunBadge,
    SectionHeader,
} from '@/components/maac/ui';
import type { Agent, Llm, Run } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';
import type { MaacData } from '@/maac/use-data';

/* ---------- local types ---------- */

type TraceState = 'done' | 'active' | 'fail' | 'pending';

interface TraceEvent {
    t: string;
    title: string;
    desc: string;
    icon: string;
    state: TraceState;
    tool?: boolean;
}

/* ---------- local sub-components ---------- */

interface MetricBoxProps {
    label: string;
    value: React.ReactNode;
    mono?: boolean;
}

function MetricBox({ label, value, mono }: MetricBoxProps) {
    return (
        <Card style={{ padding: '11px 13px' }}>
            <div
                style={{
                    fontSize: 10.5,
                    color: 'var(--text-3)',
                    fontWeight: 600,
                    textTransform: 'uppercase',
                    letterSpacing: 0.3,
                    marginBottom: 5,
                }}
            >
                {label}
            </div>
            <div
                className={mono ? 'mono tnum' : 'tnum'}
                style={{ fontSize: 15, fontWeight: 700 }}
            >
                {value}
            </div>
        </Card>
    );
}

interface CheckLineProps {
    ok: boolean;
    label: string;
}

function CheckLine({ ok, label }: CheckLineProps) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                fontSize: 12,
            }}
        >
            <Icon
                name={ok ? 'check2' : 'xCircle'}
                size={15}
                style={{
                    color: ok ? 'var(--teal-600)' : 'var(--red-600)',
                    flexShrink: 0,
                }}
            />
            <span style={{ color: 'var(--text-2)' }}>{label}</span>
        </div>
    );
}

function buildTrace(
    run: Run,
    ag: Agent | undefined,
    MAAC: MaacData,
): TraceEvent[] {
    const toolName = run.tools[0]
        ? (MAAC.toolById(run.tools[0])?.name ?? 'getOperationalRecords')
        : 'getOperationalRecords';
    const isClient = run.tools[0]
        ? MAAC.toolById(run.tools[0])?.execMode === 'client'
        : true;
    const llm = MAAC.llmById(run.llm) as Llm | undefined;
    const base: TraceEvent[] = [
        {
            t: '+0ms',
            title: 'Run started',
            desc: `Run ${run.id} accepted from ${run.caller}`,
            icon: 'play',
            state: 'done',
        },
        {
            t: '+40ms',
            title: 'Agent initialized',
            desc: `${ag?.name} ${ag?.version} loaded`,
            icon: 'agents',
            state: 'done',
        },
        {
            t: '+90ms',
            title: 'LLM selected',
            desc: `${llm?.name} · temp ${ag?.temp}`,
            icon: 'llm',
            state: 'done',
        },
        {
            t: '+820ms',
            title: 'Prompt processed',
            desc: `${run.tokensIn.toLocaleString()} input tokens`,
            icon: 'doc',
            state: 'done',
        },
    ];

    if (run.tools.length) {
        base.push({
            t: '+1.4s',
            title: `Tool required: ${toolName}`,
            desc: isClient
                ? 'Agent requested a client-side tool'
                : 'Agent invoked a MAAC-hosted tool',
            icon: 'tools',
            state: 'done',
            tool: true,
        });

        if (isClient) {
            base.push({
                t: '+1.5s',
                title: 'Waiting for client-side execution',
                desc: 'MAAC paused and returned a tool request to the application SDK',
                icon: 'clock',
                state:
                    run.status === 'waiting_for_client'
                        ? 'active'
                        : run.status === 'expired'
                          ? 'fail'
                          : 'done',
            });
        }
    }

    if (run.status === 'waiting_for_client') {
        base.push({
            t: 'now',
            title: 'Awaiting tool result',
            desc: 'The application SDK has not yet returned the result',
            icon: 'clock',
            state: 'pending',
        });
    } else if (run.status === 'expired') {
        base.push({
            t: '+60s',
            title: 'Run expired',
            desc: run.error ?? '',
            icon: 'xCircle',
            state: 'fail',
        });
    } else if (run.status === 'failed') {
        base.push({
            t: run.latency,
            title: 'Validation failed',
            desc: run.error ?? '',
            icon: 'xCircle',
            state: 'fail',
        });
    } else if (run.status === 'running') {
        base.push({
            t: 'now',
            title: 'Agent reasoning',
            desc: 'Processing tool results',
            icon: 'refresh',
            state: 'pending',
        });
    } else if (run.status === 'cancelled') {
        base.push({
            t: run.latency,
            title: 'Run cancelled',
            desc: 'Cancelled by caller',
            icon: 'x',
            state: 'fail',
        });
    } else {
        if (run.tools.length) {
            base.push({
                t: '+3.1s',
                title: 'Tool result received',
                desc: 'Result validated against output schema',
                icon: 'check2',
                state: 'done',
            });
            base.push({
                t: '+3.2s',
                title: 'Agent resumed',
                desc: 'MAAC resumed the run with the tool result',
                icon: 'refresh',
                state: 'done',
            });
        }

        base.push({
            t: `+${run.latency.replace('s', '')}`,
            title: 'Final response generated',
            desc: `${run.tokensOut.toLocaleString()} output tokens`,
            icon: 'sparkles',
            state: 'done',
        });
        base.push({
            t: run.latency,
            title: 'Run completed',
            desc: `Total latency ${run.latency} · $${run.cost.toFixed(4)}`,
            icon: 'check2',
            state: 'done',
        });
    }

    return base;
}

interface TraceTimelineProps {
    events: TraceEvent[];
}

function TraceTimeline({ events }: TraceTimelineProps) {
    const colorOf = (s: TraceState): string =>
        s === 'done'
            ? 'var(--teal-600)'
            : s === 'active'
              ? 'var(--orange-600)'
              : s === 'fail'
                ? 'var(--red-600)'
                : 'var(--blue-500)';
    const bgOf = (s: TraceState): string =>
        s === 'done'
            ? 'var(--teal-100)'
            : s === 'active'
              ? 'var(--orange-100)'
              : s === 'fail'
                ? 'var(--red-100)'
                : 'var(--blue-100)';

    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {events.map((e, i) => (
                <div key={i} style={{ display: 'flex', gap: 13 }}>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                        }}
                    >
                        <span
                            style={
                                {
                                    width: 30,
                                    height: 30,
                                    borderRadius: 999,
                                    background: bgOf(e.state),
                                    color: colorOf(e.state),
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    flexShrink: 0,
                                    border:
                                        e.state === 'pending' ||
                                        e.state === 'active'
                                            ? `2px solid ${colorOf(e.state)}`
                                            : 'none',
                                    animation:
                                        e.state === 'pending' ||
                                        e.state === 'active'
                                            ? 'pulseDot 1.6s infinite'
                                            : 'none',
                                } as CSSProperties
                            }
                        >
                            <Icon name={e.icon} size={15} />
                        </span>
                        {i < events.length - 1 && (
                            <span
                                style={{
                                    flex: 1,
                                    width: 2,
                                    background:
                                        e.state === 'fail'
                                            ? 'var(--red-100)'
                                            : 'var(--border)',
                                    minHeight: 18,
                                }}
                            />
                        )}
                    </div>
                    <div
                        style={{
                            paddingBottom: i < events.length - 1 ? 16 : 0,
                            flex: 1,
                            minWidth: 0,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                flexWrap: 'wrap',
                            }}
                        >
                            <span style={{ fontSize: 13, fontWeight: 600 }}>
                                {e.title}
                            </span>
                            <span
                                className="mono"
                                style={{ fontSize: 11, color: 'var(--text-3)' }}
                            >
                                {e.t}
                            </span>
                            {e.tool && (
                                <Badge tone="orange" soft>
                                    tool call
                                </Badge>
                            )}
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: 'var(--text-3)',
                                marginTop: 2,
                            }}
                        >
                            {e.desc}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

/* ---------- page ---------- */

export default function Show({ id }: { id: string }) {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const run = MAAC.byId(MAAC.runs, id) || MAAC.runs[0];

    if (!scope.has.run(run.id)) {
        return <NoAccess kind="run" />;
    }

    const ag = MAAC.agentById(run.agentId);
    const llm = MAAC.llmById(run.llm);
    const failed = ['failed', 'expired'].includes(run.status);

    const events = buildTrace(run, ag, MAAC);

    const finalOutput: string | null =
        run.status === 'completed' ? (run.output ?? null) : null;

    return (
        <>
            <Head title={run.id} />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        {
                            label: 'Runs & Audit Logs',
                            onClick: () => go('runs'),
                        },
                        { label: run.id },
                    ]}
                    title={
                        <span className="mono" style={{ fontSize: 21 }}>
                            {run.id}
                        </span>
                    }
                    badge={<RunBadge status={run.status} dot />}
                    sub={
                        <span>
                            {ag?.name} · {MAAC.appById(run.appId)?.name} ·
                            called by <span className="mono">{run.caller}</span>
                        </span>
                    }
                    actions={
                        <>
                            <Btn variant="default" icon="copy">
                                Copy trace
                            </Btn>
                            <Btn
                                variant="default"
                                icon="agents"
                                onClick={() => go('agent', { id: run.agentId })}
                            >
                                Open agent
                            </Btn>
                        </>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(6,1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <MetricBox
                        label="Status"
                        value={<RunBadge status={run.status} />}
                    />
                    <MetricBox label="Model" value={llm?.name} mono />
                    <MetricBox
                        label="Input tokens"
                        value={run.tokensIn.toLocaleString()}
                        mono
                    />
                    <MetricBox
                        label="Output tokens"
                        value={run.tokensOut.toLocaleString()}
                        mono
                    />
                    <MetricBox
                        label="Cost"
                        value={`$${run.cost.toFixed(4)}`}
                        mono
                    />
                    <MetricBox label="Latency" value={run.latency} mono />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 360px',
                        gap: 14,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader title="User input" icon="user" />
                            <div
                                style={{
                                    padding: '12px 14px',
                                    background: 'var(--surface-2)',
                                    borderRadius: 'var(--r-md)',
                                    border: '1px solid var(--border)',
                                    fontSize: 13,
                                    lineHeight: 1.55,
                                }}
                            >
                                {run.input}
                            </div>
                        </Card>

                        {failed && (
                            <Card style={{ borderColor: 'var(--red-500)' }}>
                                <SectionHeader title="Error" icon="xCircle" />
                                <div
                                    style={{
                                        padding: '12px 14px',
                                        background: 'var(--red-100)',
                                        borderRadius: 'var(--r-md)',
                                        fontSize: 12.5,
                                        color: 'var(--red-600)',
                                        fontFamily: 'var(--mono)',
                                        lineHeight: 1.55,
                                    }}
                                >
                                    {run.error}
                                </div>
                            </Card>
                        )}

                        {finalOutput && (
                            <Card style={{ borderColor: 'var(--teal-300)' }}>
                                <SectionHeader
                                    title="Final response"
                                    icon="check2"
                                    right={
                                        <Badge tone="teal" dot>
                                            completed
                                        </Badge>
                                    }
                                />
                                <div
                                    style={{
                                        padding: '12px 14px',
                                        background: 'var(--teal-100)',
                                        borderRadius: 'var(--r-md)',
                                        fontSize: 13,
                                        lineHeight: 1.6,
                                        color: 'var(--text)',
                                    }}
                                >
                                    {finalOutput}
                                </div>
                            </Card>
                        )}

                        <Card pad={false}>
                            <div style={{ padding: '14px 16px 4px' }}>
                                <SectionHeader
                                    title="Execution timeline"
                                    sub="Observability trace of the agent run"
                                    icon="runs"
                                    style={{ marginBottom: 0 }}
                                />
                            </div>
                            <div style={{ padding: '14px 16px 16px' }}>
                                <TraceTimeline events={events} />
                            </div>
                        </Card>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader title="Run metadata" icon="info" />
                            <KV
                                cols={1}
                                items={[
                                    { k: 'Run ID', v: run.id, mono: true },
                                    { k: 'Agent', v: ag?.name },
                                    {
                                        k: 'Application',
                                        v: MAAC.appById(run.appId)?.name,
                                    },
                                    {
                                        k: 'Project',
                                        v: MAAC.projectById(run.projectId)
                                            ?.name,
                                    },
                                    { k: 'Caller', v: run.caller, mono: true },
                                    { k: 'Model', v: llm?.code, mono: true },
                                    {
                                        k: 'Started',
                                        v: run.started,
                                        mono: true,
                                    },
                                    {
                                        k: 'Completed',
                                        v: run.completed,
                                        mono: true,
                                    },
                                ]}
                            />
                        </Card>
                        <Card>
                            <SectionHeader title="Tool calls" icon="tools" />
                            {run.tools.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    No tools were called.
                                </div>
                            )}
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                }}
                            >
                                {run.tools.map((t) => {
                                    const tool = MAAC.toolById(t);

                                    return (
                                        <div
                                            key={t}
                                            className="maac-row"
                                            onClick={() =>
                                                go('tool', { id: t })
                                            }
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 9,
                                                padding: '8px 9px',
                                                borderRadius: 7,
                                                cursor: 'pointer',
                                                border: '1px solid var(--border)',
                                            }}
                                        >
                                            <Icon
                                                name={
                                                    tool?.execMode === 'client'
                                                        ? 'link'
                                                        : 'tools'
                                                }
                                                size={15}
                                                style={{
                                                    color:
                                                        tool?.execMode ===
                                                        'client'
                                                            ? 'var(--orange-600)'
                                                            : 'var(--primary)',
                                                }}
                                            />
                                            <span
                                                className="mono"
                                                style={{
                                                    fontSize: 12,
                                                    flex: 1,
                                                }}
                                            >
                                                {tool?.name}
                                            </span>
                                            {tool?.execMode === 'client' ? (
                                                <Badge tone="orange" soft>
                                                    client
                                                </Badge>
                                            ) : (
                                                <Badge tone="purple" soft>
                                                    hosted
                                                </Badge>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>
                        <Card>
                            <SectionHeader
                                title="Audit & compliance"
                                icon="shield"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 9,
                                }}
                            >
                                <CheckLine
                                    ok
                                    label="Caller authenticated via app credentials"
                                />
                                <CheckLine
                                    ok
                                    label="Tool args validated against schema"
                                />
                                <CheckLine
                                    ok={!failed}
                                    label="Tool result validated against schema"
                                />
                                <CheckLine
                                    ok
                                    label="Sensitive fields masked in log"
                                />
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
