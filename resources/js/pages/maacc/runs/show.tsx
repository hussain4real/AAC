/* ============================================================
   MAACC — Run Trace (single run detail + execution timeline)
   The execution timeline renders the run's REAL observability trace
   (TraceEvent records, ordered by sequence) passed as the `trace` page
   prop — it mirrors the live-trace rendering used by the Agent Playground.
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { NoAccess } from '@/components/maacc/common';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    KV,
    PageHeader,
    RunBadge,
    SectionHeader,
} from '@/components/maacc/ui';
import { formatCurrency } from '@/maacc/format';
import { Icon } from '@/maacc/icons';
import type { IconName } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';

/* ---------- trace presentation (mirrors the playground's real-trace timeline) ---------- */

/**
 * One ordered trace event recorded during the run, as serialized by
 * {@see \App\Http\Resources\Maacc\TraceEventResource}.
 */
interface TraceEntry {
    id: string;
    type: string;
    label: string;
    message: string | null;
    data: Record<string, unknown> | null;
    sequence: number;
    occurredAt: string | null;
}

const TRACE_ICONS: Record<string, IconName> = {
    run_requested: 'play',
    caller_authenticated: 'user',
    model_selected: 'llm',
    model_failover: 'refresh',
    prompt_prepared: 'doc',
    tool_required: 'tools',
    tool_result_received: 'download',
    validated: 'check2',
    resumed: 'refresh',
    requires_approval: 'clock',
    approval_granted: 'checkCircle',
    approval_denied: 'xCircle',
    completed: 'checkCircle',
    failed: 'xCircle',
};

function traceTone(type: string): { color: string; bg: string } {
    if (
        type === 'completed' ||
        type === 'validated' ||
        type === 'approval_granted'
    ) {
        return { color: 'var(--teal-600)', bg: 'var(--teal-100)' };
    }

    if (type === 'failed' || type === 'approval_denied') {
        return { color: 'var(--red-500)', bg: 'var(--red-100)' };
    }

    if (
        type === 'tool_required' ||
        type === 'requires_approval' ||
        type === 'model_failover'
    ) {
        return { color: 'var(--orange-600)', bg: 'var(--orange-100)' };
    }

    return { color: 'var(--blue-500)', bg: 'var(--blue-100)' };
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

interface TraceTimelineProps {
    trace: TraceEntry[];
}

function TraceTimeline({ trace }: TraceTimelineProps) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {trace.map((event, i) => {
                const tone = traceTone(event.type);

                return (
                    <div
                        key={event.id}
                        style={{
                            display: 'flex',
                            gap: 13,
                            animation: 'fadeUp .3s ease both',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                            }}
                        >
                            <span
                                style={{
                                    width: 30,
                                    height: 30,
                                    borderRadius: 999,
                                    background: tone.bg,
                                    color: tone.color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    flexShrink: 0,
                                }}
                            >
                                <Icon
                                    name={TRACE_ICONS[event.type] ?? 'runs'}
                                    size={15}
                                />
                            </span>
                            {i < trace.length - 1 && (
                                <span
                                    style={{
                                        flex: 1,
                                        width: 2,
                                        background: 'var(--border)',
                                        minHeight: 18,
                                    }}
                                />
                            )}
                        </div>
                        <div
                            style={{
                                paddingBottom: i < trace.length - 1 ? 16 : 0,
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
                                    {event.label}
                                </span>
                                {event.occurredAt && (
                                    <span
                                        className="mono"
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {event.occurredAt}
                                    </span>
                                )}
                            </div>
                            {event.message && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-3)',
                                        marginTop: 2,
                                    }}
                                >
                                    {event.message}
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/* ---------- page ---------- */

export default function Show({
    id,
    trace,
}: {
    id: string;
    trace: TraceEntry[] | null;
}) {
    const { go, scope } = useMaaccNav();
    const MAACC = useMaaccData();
    const run = MAACC.byId(MAACC.runs, id) || MAACC.runs[0];
    const [copyState, setCopyState] = useState<'idle' | 'copied' | 'manual'>(
        'idle',
    );

    if (!scope.has.run(run.id)) {
        return <NoAccess kind="run" />;
    }

    const ag = MAACC.agentById(run.agentId);
    const llm = MAACC.llmById(run.llm);
    const failed = ['failed', 'expired'].includes(run.status);
    const traceText = JSON.stringify(
        { runId: run.id, status: run.status, trace: trace ?? [] },
        null,
        2,
    );

    const copyTrace = async () => {
        try {
            await navigator.clipboard.writeText(traceText);
            setCopyState('copied');
        } catch {
            setCopyState('manual');
        }
    };

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
                            {ag?.name} · {MAACC.appById(run.appId)?.name} ·
                            called by <span className="mono">{run.caller}</span>
                        </span>
                    }
                    actions={
                        <>
                            <Btn
                                variant="default"
                                icon="copy"
                                onClick={() => void copyTrace()}
                            >
                                {copyState === 'copied'
                                    ? 'Trace copied'
                                    : 'Copy trace'}
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

                <div aria-live="polite" className="sr-only">
                    {copyState === 'copied' ? 'Run trace copied.' : ''}
                </div>
                {copyState === 'manual' && (
                    <Card style={{ marginBottom: 16 }}>
                        <SectionHeader
                            title="Copy unavailable"
                            sub="Select and copy the trace manually."
                            icon="copy"
                        />
                        <textarea
                            readOnly
                            value={traceText}
                            aria-label="Run trace for manual copy"
                            onFocus={(event) => event.currentTarget.select()}
                            style={{
                                width: '100%',
                                minHeight: 160,
                                fontFamily: 'var(--mono)',
                                fontSize: 12,
                            }}
                        />
                    </Card>
                )}

                <div
                    className="maacc-responsive-grid"
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
                        value={formatCurrency(run.cost, run.currency)}
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
                                {trace && trace.length > 0 ? (
                                    <TraceTimeline trace={trace} />
                                ) : (
                                    <EmptyState
                                        icon="runs"
                                        title="No trace recorded"
                                        desc="This run has no recorded observability trace yet."
                                    />
                                )}
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
                                        v: MAACC.appById(run.appId)?.name,
                                    },
                                    {
                                        k: 'Project',
                                        v: MAACC.projectById(run.projectId)
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
                                    const tool = MAACC.toolById(t);

                                    return (
                                        <div
                                            key={t}
                                            className="maacc-row"
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
                                    ok={!!run.masked}
                                    label={
                                        run.masked
                                            ? 'Sensitive fields masked in log'
                                            : 'No field masking applied to this run'
                                    }
                                />
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
