/* ============================================================
   MAAC — Agent Playground (client-side tool execution)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Lbl } from '@/components/maac/common';
import {
    AgentBadge,
    Avatar,
    Badge,
    Btn,
    Card,
    CodeBlock,
    EmptyState,
    ExecChip,
    Field,
    PageHeader,
    SectionHeader,
    Select,
    SensBadge,
    Textarea,
} from '@/components/maac/ui';
import type { Agent, Application, Tool } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ── module consts ── */

const PLAY_STEPS = [
    { id: 'start', label: 'Run started', icon: 'play' },
    { id: 'init', label: 'Agent initialized', icon: 'agents' },
    { id: 'llm', label: 'LLM selected', icon: 'llm' },
    { id: 'prompt', label: 'Prompt processed', icon: 'doc' },
    { id: 'toolreq', label: 'Tool required', icon: 'tools' },
    {
        id: 'waiting',
        label: 'Waiting for client-side execution',
        icon: 'clock',
    },
    { id: 'result', label: 'Tool result received', icon: 'download' },
    { id: 'resume', label: 'Agent resumed', icon: 'refresh' },
    { id: 'final', label: 'Final response generated', icon: 'sparkles' },
    { id: 'done', label: 'Run completed', icon: 'check2' },
];

interface ScenarioArgs {
    from_date?: string;
    to_date?: string;
    status?: string;
    queue?: string;
    assignee_id?: string;
    limit?: number;
    channel?: string;
}

interface ScenarioResult {
    summary?: Record<string, string | number>;
    records?: Record<string, string | number | null>[];
    items?: Record<string, string | number | boolean>[];
    total?: number;
    requests?: Record<string, string | number>[];
    interactions?: Record<string, string | number>[];
}

interface Scenario {
    args: ScenarioArgs;
    reason: string;
    result: ScenarioResult;
    final: string;
}

const SCENARIOS: Record<string, Scenario> = {
    getOperationalRecords: {
        args: {
            from_date: '2026-06-08',
            to_date: '2026-06-08',
            status: 'active',
        },
        reason: "To summarize today's operations, the agent needs the current operational voyage records. This data lives in the Marine Operations Portal database — MAAC cannot access it directly, so it requests the application to run the tool.",
        result: {
            summary: {
                total_vessels: 12,
                delayed_over_6h: 2,
                avg_berth_utilization: '84%',
            },
            records: [
                {
                    vessel: 'MV Al-Zubarah',
                    port: 'Hamad',
                    status: 'delayed',
                    delay: '7h10m',
                    reason: 'berth congestion',
                },
                {
                    vessel: 'MV Doha Pearl',
                    port: 'Doha',
                    status: 'delayed',
                    delay: '6h40m',
                    reason: 'customs hold',
                },
                {
                    vessel: 'MV Umm Salal',
                    port: 'Hamad',
                    status: 'on_time',
                    delay: '0',
                    reason: null,
                },
            ],
        },
        final: '12 vessels are active across Hamad and Doha ports. 2 voyages exceed the 6-hour delay threshold: MV Al-Zubarah (berth congestion, +7h10m) and MV Doha Pearl (customs hold, +6h40m). Berth utilization at Hamad is 84%. Recommended action: reallocate Berth 7 to MV Al-Zubarah to recover schedule.',
    },
    getPendingApprovals: {
        args: { queue: 'finance-approvals', assignee_id: 'u_8821', limit: 25 },
        reason: "The agent needs the user's pending approval items to review them. These are held in the Finance Workflow System — the application executes the lookup against its own data and the caller's permissions.",
        result: {
            items: [
                {
                    id: 'AP-4821',
                    type: 'Invoice',
                    amount: 'QAR 42,500',
                    vendor: 'Gulf Marine Supplies',
                    policy_ok: true,
                },
                {
                    id: 'AP-4830',
                    type: 'Payment Run',
                    amount: 'QAR 188,000',
                    vendor: 'Doha Logistics Co',
                    policy_ok: false,
                },
            ],
            total: 2,
        },
        final: 'You have 2 pending approvals. AP-4821 (QAR 42,500, Gulf Marine Supplies) is within policy — recommend Approve. AP-4830 (QAR 188,000) exceeds the QAR 150,000 single-approval threshold — recommend Escalate to the Finance Controller. A notification was sent to the workflow owner.',
    },
    getProcurementRequests: {
        args: {
            from_date: '2026-01-01',
            to_date: '2026-03-31',
            status: 'delivered',
        },
        reason: "To analyze supplier delivery performance, the agent requests last quarter's procurement records. The Procurement Management App owns this data and runs the query locally.",
        result: {
            summary: {
                total_requests: 430,
                late_deliveries: 38,
                worst_supplier: 'Bayside Traders',
            },
            requests: [
                { supplier: 'Bayside Traders', on_time_rate: '71%', late: 14 },
                {
                    supplier: 'Gulf Marine Supplies',
                    on_time_rate: '96%',
                    late: 3,
                },
            ],
        },
        final: 'Across 430 procurement requests last quarter, 38 deliveries were late (8.8%). Bayside Traders had the weakest on-time rate at 71% (14 late deliveries) and is the top contributor to delays. Gulf Marine Supplies performed best at 96%. Recommend a supplier review meeting with Bayside Traders.',
    },
    getCustomerInteractions: {
        args: {
            from_date: '2026-06-01',
            to_date: '2026-06-08',
            channel: 'all',
        },
        reason: "The agent needs this week's customer interactions to surface emerging themes. The Customer Service Portal returns anonymized records from its own database.",
        result: {
            summary: {
                total: 1840,
                top_theme: 'shipment delays',
                sentiment: '-0.18 vs last week',
            },
            interactions: [
                { theme: 'Shipment delays', count: 312, sentiment: 'negative' },
                {
                    theme: 'Billing questions',
                    count: 154,
                    sentiment: 'neutral',
                },
            ],
        },
        final: 'From 1,840 interactions this week, the top emerging theme is shipment delays (312 mentions, mostly negative). Sentiment dropped 0.18 vs last week, driven by port congestion complaints. Billing questions (154) are stable. Recommend a proactive status notification for delayed shipments.',
    },
};

const DEFAULT_SCENARIO = SCENARIOS.getOperationalRecords;

/* ── local sub-components ── */

type Phase = 'idle' | 'pre' | 'waiting' | 'post' | 'done';

interface ToolExecPanelProps {
    tool: Tool;
    scenario: Scenario;
    phase: Phase;
    showResult: boolean;
    onSimulate: () => void;
    app: Application | undefined;
}

function ToolExecPanel({
    tool,
    scenario,
    phase,
    showResult,
    onSimulate,
    app,
}: ToolExecPanelProps) {
    const waiting = phase === 'waiting';

    return (
        <Card
            pad={false}
            style={{
                overflow: 'hidden',
                borderColor: waiting ? 'var(--orange-400)' : 'var(--teal-300)',
                borderWidth: 1.5,
                animation: 'fadeUp .35s ease both',
            }}
        >
            <div
                style={{
                    padding: '13px 16px',
                    background: waiting
                        ? 'linear-gradient(100deg, var(--orange-100), transparent)'
                        : 'linear-gradient(100deg, var(--teal-100), transparent)',
                    borderBottom: '1px solid var(--border)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 11,
                }}
            >
                <span
                    style={{
                        width: 36,
                        height: 36,
                        borderRadius: 9,
                        background: waiting
                            ? 'var(--orange-600)'
                            : 'var(--teal-600)',
                        color: '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                        animation: waiting ? 'pulseDot 1.8s infinite' : 'none',
                    }}
                >
                    <Icon name="link" size={19} />
                </span>
                <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 700 }}>
                        Client-Side Tool Execution{' '}
                        {waiting ? 'Required' : 'Completed'}
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            marginTop: 1,
                        }}
                    >
                        MAAC paused the run and{' '}
                        {waiting ? 'is waiting for' : 'received a result from'}{' '}
                        <b>{app?.name}</b>
                    </div>
                </div>
                <Badge tone={waiting ? 'orange' : 'teal'} dot>
                    {waiting ? 'Waiting for Application' : 'Result received'}
                </Badge>
            </div>

            <div style={{ padding: '15px 16px' }}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                        marginBottom: 14,
                    }}
                >
                    <div>
                        <Lbl>Tool</Lbl>
                        <div
                            className="mono"
                            style={{ fontSize: 13, fontWeight: 600 }}
                        >
                            {tool.name}
                        </div>
                        <div style={{ display: 'flex', gap: 6, marginTop: 7 }}>
                            <ExecChip mode="client" />
                            <SensBadge level={tool.sensitivity} />
                        </div>
                    </div>
                    <div>
                        <Lbl>Why the agent needs this</Lbl>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--text-2)',
                                lineHeight: 1.5,
                            }}
                        >
                            {scenario.reason}
                        </div>
                    </div>
                </div>

                <Lbl>Tool arguments (from agent)</Lbl>
                <CodeBlock
                    code={JSON.stringify(scenario.args, null, 2)}
                    lang="json"
                    copyable={false}
                    style={{ marginBottom: waiting || showResult ? 14 : 0 }}
                />

                {waiting && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '13px 15px',
                            background: 'var(--orange-100)',
                            borderRadius: 'var(--r-md)',
                            border: '1px solid var(--orange-400)',
                        }}
                    >
                        <div
                            style={{
                                flex: 1,
                                fontSize: 12,
                                color: 'var(--text-2)',
                                lineHeight: 1.5,
                            }}
                        >
                            MAAC returned this tool request to the application's
                            SDK. In production, the application would run its
                            local handler against its own database.{' '}
                            <b>Simulate that execution below.</b>
                        </div>
                        <Btn
                            variant="primary"
                            icon="play"
                            onClick={onSimulate}
                            style={{ flexShrink: 0 }}
                        >
                            Simulate Client Tool Execution
                        </Btn>
                    </div>
                )}

                {showResult && (
                    <div style={{ animation: 'fadeUp .4s ease both' }}>
                        <Lbl style={{ marginTop: 2 }}>
                            Tool result (returned by application SDK)
                        </Lbl>
                        <CodeBlock
                            code={JSON.stringify(scenario.result, null, 2)}
                            lang="json"
                            maxHeight={220}
                        />
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                marginTop: 11,
                                fontSize: 12,
                                color: 'var(--teal-600)',
                                fontWeight: 600,
                            }}
                        >
                            <Icon name="check2" size={16} /> Result validated
                            against output schema · MAAC resumed the run
                        </div>
                    </div>
                )}
            </div>
        </Card>
    );
}

interface PlaygroundTimelineProps {
    visible: number;
    phase: Phase;
    agent: Agent;
    tool: Tool | undefined;
}

function PlaygroundTimeline({
    visible,
    phase,
    agent,
    tool,
}: PlaygroundTimelineProps) {
    const MAAC = useMaacData();

    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {PLAY_STEPS.map((s, i) => {
                const shown = i < visible;
                const isActive =
                    i === visible - 1 && (phase === 'pre' || phase === 'post');
                const isWaiting = s.id === 'waiting' && phase === 'waiting';
                const detail: Record<string, string | undefined> = {
                    start: 'Run accepted',
                    init: `${agent.name} ${agent.version}`,
                    llm: MAAC.llmById(agent.llm)?.name,
                    prompt: 'System prompt + user message tokenized',
                    toolreq: tool ? tool.name : '—',
                    waiting:
                        'MAAC returned a tool request to the application SDK',
                    result: 'Validated against output schema',
                    resume: 'Run resumed with tool result',
                    final: 'Response composed',
                    done: 'Latency 4.2s · $0.0162',
                };
                const color = isWaiting
                    ? 'var(--orange-600)'
                    : isActive
                      ? 'var(--blue-500)'
                      : shown
                        ? 'var(--teal-600)'
                        : 'var(--text-3)';
                const bg = isWaiting
                    ? 'var(--orange-100)'
                    : isActive
                      ? 'var(--blue-100)'
                      : shown
                        ? 'var(--teal-100)'
                        : 'var(--surface-3)';

                return (
                    <div
                        key={s.id}
                        style={{
                            display: 'flex',
                            gap: 13,
                            opacity: shown ? 1 : 0.4,
                            transition: 'opacity .3s',
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
                                    background: bg,
                                    color,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    flexShrink: 0,
                                    border:
                                        isWaiting || isActive
                                            ? `2px solid ${color}`
                                            : 'none',
                                    animation:
                                        isWaiting || isActive
                                            ? 'pulseDot 1.5s infinite'
                                            : 'none',
                                    transition: 'all .3s',
                                }}
                            >
                                {shown ? (
                                    <Icon name={s.icon} size={15} />
                                ) : (
                                    <span
                                        style={{
                                            fontSize: 11,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {i + 1}
                                    </span>
                                )}
                            </span>
                            {i < PLAY_STEPS.length - 1 && (
                                <span
                                    style={{
                                        flex: 1,
                                        width: 2,
                                        background:
                                            shown && i < visible - 1
                                                ? 'var(--teal-300)'
                                                : 'var(--border)',
                                        minHeight: 16,
                                        transition: 'background .3s',
                                    }}
                                />
                            )}
                        </div>
                        <div
                            style={{
                                paddingBottom:
                                    i < PLAY_STEPS.length - 1 ? 14 : 0,
                                flex: 1,
                                minWidth: 0,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        color: shown
                                            ? 'var(--text)'
                                            : 'var(--text-3)',
                                    }}
                                >
                                    {i + 1}. {s.label}
                                </span>
                                {isWaiting && (
                                    <Badge tone="orange" dot>
                                        paused
                                    </Badge>
                                )}
                                {s.id === 'toolreq' && shown && (
                                    <Badge tone="orange" soft>
                                        client-side
                                    </Badge>
                                )}
                            </div>
                            {shown && (
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: 'var(--text-3)',
                                        marginTop: 2,
                                    }}
                                >
                                    {detail[s.id]}
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/* ── page ── */

export default function Playground() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const agentParam = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    ).get('agent');
    const inScopeAgents = scope.agents.length ? scope.agents : MAAC.agents;
    const reqAgent =
        agentParam && scope.has.agent(agentParam) ? agentParam : null;
    const ag0 =
        MAAC.agentById(reqAgent ?? '') ||
        inScopeAgents.find((a) => a.id === 'ag_ops_summary') ||
        inScopeAgents[0];
    const [appId, setAppId] = useState(ag0.appId);
    const [projectId, setProjectId] = useState(ag0.projectId);
    const [agentId, setAgentId] = useState(ag0.id);
    const agent = MAAC.agentById(agentId)!;
    const clientTool = agent.tools
        .map((t) => MAAC.toolById(t))
        .find((t) => t?.execMode === 'client');
    const scenario =
        (clientTool && SCENARIOS[clientTool.id]) || DEFAULT_SCENARIO;

    const defaultMsgs: Record<string, string> = {
        ag_ops_summary:
            "Summarize today's vessel operations and flag any delays over 6 hours.",
        ag_approval_review:
            'Review my pending approvals and recommend an action for each.',
        ag_procure_insight:
            'Which suppliers had the most delayed deliveries last quarter?',
        ag_customer_trend:
            'What are the top emerging complaint themes this week?',
    };

    const [msg, setMsg] = useState(
        defaultMsgs[agentId] ||
            'Summarize the latest activity and flag anything that needs attention.',
    );
    const [phase, setPhase] = useState<Phase>('idle');
    const [visible, setVisible] = useState(0);
    const [showResult, setShowResult] = useState(false);
    const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

    const clearTimers = () => {
        timers.current.forEach(clearTimeout);
        timers.current = [];
    };
    useEffect(() => () => clearTimers(), []);

    const reset = () => {
        clearTimers();
        setPhase('idle');
        setVisible(0);
        setShowResult(false);
    };

    const onAgentChange = (id: string) => {
        const a = MAAC.agentById(id);
        setAgentId(id);

        if (a) {
            setAppId(a.appId);
            setProjectId(a.projectId);
        }

        setMsg(
            defaultMsgs[id] ||
                'Summarize the latest activity and flag anything that needs attention.',
        );
        reset();
    };

    const run = () => {
        clearTimers();
        setShowResult(false);
        setPhase('pre');
        setVisible(1);
        // reveal steps 1..6 (indices 0..5), pausing at "waiting" (index 5)
        [2, 3, 4, 5, 6].forEach((n, i) => {
            timers.current.push(
                setTimeout(
                    () => {
                        setVisible(n);

                        if (n === 6) {
                            setPhase('waiting');
                        }
                    },
                    650 * (i + 1),
                ),
            );
        });
    };

    const simulate = () => {
        setShowResult(true);
        setPhase('post');
        // reveal steps 7..10 (indices 6..9)
        [7, 8, 9, 10].forEach((n, i) => {
            timers.current.push(
                setTimeout(
                    () => {
                        setVisible(n);

                        if (n === 10) {
                            setPhase('done');
                        }
                    },
                    700 * (i + 1),
                ),
            );
        });
    };

    return (
        <>
            <Head title="Agent Playground" />
            <div className="route-anim">
                <PageHeader
                    title="Agent Playground"
                    sub="Test an agent end-to-end and watch the client-side tool execution pause-and-resume flow in real time."
                    actions={
                        <>
                            {phase !== 'idle' && (
                                <Btn
                                    variant="default"
                                    icon="refresh"
                                    onClick={reset}
                                >
                                    Reset
                                </Btn>
                            )}
                            <Btn
                                variant="default"
                                icon="agents"
                                onClick={() => go('agent', { id: agentId })}
                            >
                                Open agent
                            </Btn>
                        </>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '340px 1fr',
                        gap: 14,
                    }}
                >
                    {/* config */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader
                                title="Configuration"
                                icon="settings"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 13,
                                }}
                            >
                                <Field label="Application">
                                    <Select
                                        value={appId}
                                        onChange={(v) => {
                                            setAppId(v);
                                            const a = inScopeAgents.find(
                                                (x) => x.appId === v,
                                            );

                                            if (a) {
                                                onAgentChange(a.id);
                                            }
                                        }}
                                        options={scope.apps.map((a) => ({
                                            value: a.id,
                                            label: a.name,
                                        }))}
                                    />
                                </Field>
                                <Field label="Project">
                                    <Select
                                        value={projectId}
                                        onChange={setProjectId}
                                        options={scope.projects
                                            .filter((p) => p.appId === appId)
                                            .map((p) => ({
                                                value: p.id,
                                                label: p.name,
                                            }))}
                                    />
                                </Field>
                                <Field label="Agent">
                                    <Select
                                        value={agentId}
                                        onChange={onAgentChange}
                                        options={inScopeAgents
                                            .filter((a) => a.appId === appId)
                                            .map((a) => ({
                                                value: a.id,
                                                label: a.name,
                                            }))}
                                    />
                                </Field>
                            </div>
                            <div
                                style={{
                                    marginTop: 13,
                                    padding: '10px 12px',
                                    background: 'var(--surface-2)',
                                    borderRadius: 'var(--r-md)',
                                    border: '1px solid var(--border)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                }}
                            >
                                <Badge tone="purple" soft icon="llm">
                                    {MAAC.llmById(agent.llm)?.name}
                                </Badge>
                                <Badge tone="neutral">
                                    {agent.tools.length} tools
                                </Badge>
                                <AgentBadge status={agent.status} />
                            </div>
                        </Card>

                        <Card>
                            <SectionHeader title="User message" icon="user" />
                            <Textarea
                                value={msg}
                                onChange={(e) => setMsg(e.target.value)}
                                rows={4}
                                placeholder="Enter a message to send to the agent…"
                                disabled={phase !== 'idle' && phase !== 'done'}
                            />
                            <Btn
                                variant="primary"
                                full
                                icon={
                                    phase === 'pre' || phase === 'post'
                                        ? 'refresh'
                                        : 'play'
                                }
                                style={{ marginTop: 12 }}
                                disabled={
                                    phase === 'pre' ||
                                    phase === 'post' ||
                                    phase === 'waiting'
                                }
                                onClick={run}
                            >
                                {phase === 'idle'
                                    ? 'Run Agent'
                                    : phase === 'done'
                                      ? 'Run Again'
                                      : 'Running…'}
                            </Btn>
                            <div style={{ marginTop: 12 }}>
                                <div
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 600,
                                        color: 'var(--text-3)',
                                        marginBottom: 7,
                                    }}
                                >
                                    Try an example
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 6,
                                    }}
                                >
                                    {Object.values(defaultMsgs)
                                        .slice(0, 3)
                                        .map((m, i) => (
                                            <button
                                                key={i}
                                                onClick={() => {
                                                    if (
                                                        phase === 'idle' ||
                                                        phase === 'done'
                                                    ) {
                                                        setMsg(m);
                                                        reset();
                                                    }
                                                }}
                                                className="maac-row"
                                                style={{
                                                    textAlign: 'left',
                                                    border: '1px solid var(--border)',
                                                    background:
                                                        'var(--surface)',
                                                    borderRadius: 7,
                                                    padding: '7px 10px',
                                                    fontSize: 11.5,
                                                    color: 'var(--text-2)',
                                                    cursor: 'pointer',
                                                }}
                                            >
                                                {m}
                                            </button>
                                        ))}
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* execution */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                            minWidth: 0,
                        }}
                    >
                        {phase === 'idle' ? (
                            <Card style={{ minHeight: 480 }}>
                                <EmptyState
                                    icon="playground"
                                    title="Ready to run"
                                    desc="Configure an agent and press Run. You'll watch MAAC pause for a client-side tool, then simulate the application returning the result."
                                    action={
                                        <Btn
                                            variant="primary"
                                            icon="play"
                                            onClick={run}
                                        >
                                            Run Agent
                                        </Btn>
                                    }
                                />
                            </Card>
                        ) : (
                            <>
                                {/* conversation */}
                                <Card>
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 11,
                                            marginBottom: 14,
                                        }}
                                    >
                                        <Avatar name="Reema Saleh" size={32} />
                                        <div style={{ flex: 1 }}>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    fontWeight: 600,
                                                    marginBottom: 4,
                                                }}
                                            >
                                                You
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 13,
                                                    lineHeight: 1.55,
                                                    padding: '10px 13px',
                                                    background:
                                                        'var(--surface-2)',
                                                    borderRadius:
                                                        '0 10px 10px 10px',
                                                    border: '1px solid var(--border)',
                                                }}
                                            >
                                                {msg}
                                            </div>
                                        </div>
                                    </div>
                                    {phase === 'done' && (
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 11,
                                                animation:
                                                    'fadeUp .4s ease both',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 32,
                                                    height: 32,
                                                    borderRadius: 9,
                                                    background:
                                                        'var(--primary)',
                                                    color: 'var(--primary-contrast)',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    flexShrink: 0,
                                                }}
                                            >
                                                <Icon name="agents" size={17} />
                                            </span>
                                            <div style={{ flex: 1 }}>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        marginBottom: 4,
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 8,
                                                    }}
                                                >
                                                    {agent.name}
                                                    <Badge tone="teal" dot>
                                                        completed
                                                    </Badge>
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        lineHeight: 1.6,
                                                        padding: '11px 14px',
                                                        background:
                                                            'var(--primary-soft)',
                                                        borderRadius:
                                                            '0 10px 10px 10px',
                                                        border: '1px solid var(--primary-soft-2)',
                                                    }}
                                                >
                                                    {scenario.final}
                                                </div>
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        gap: 14,
                                                        marginTop: 8,
                                                        fontSize: 11,
                                                        color: 'var(--text-3)',
                                                    }}
                                                >
                                                    <span className="mono">
                                                        ⚡ {agent.tools.length}{' '}
                                                        tool calls
                                                    </span>
                                                    <span className="mono">
                                                        ⏱ 4.2s
                                                    </span>
                                                    <span className="mono">
                                                        $0.0162
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </Card>

                                {/* client tool execution panel */}
                                {(phase === 'waiting' ||
                                    phase === 'post' ||
                                    phase === 'done') &&
                                    clientTool && (
                                        <ToolExecPanel
                                            tool={clientTool}
                                            scenario={scenario}
                                            phase={phase}
                                            showResult={showResult}
                                            onSimulate={simulate}
                                            app={MAAC.appById(appId)}
                                        />
                                    )}

                                {/* timeline */}
                                <Card pad={false}>
                                    <div
                                        style={{
                                            padding: '13px 16px 4px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                        }}
                                    >
                                        <SectionHeader
                                            title="Execution timeline"
                                            icon="runs"
                                            style={{ marginBottom: 0 }}
                                        />
                                        <Badge
                                            tone={
                                                phase === 'done'
                                                    ? 'teal'
                                                    : phase === 'waiting'
                                                      ? 'orange'
                                                      : 'blue'
                                            }
                                            dot
                                        >
                                            {phase === 'waiting'
                                                ? 'Paused — waiting for client'
                                                : phase === 'done'
                                                  ? 'Completed'
                                                  : 'Running'}
                                        </Badge>
                                    </div>
                                    <div style={{ padding: '14px 16px 16px' }}>
                                        <PlaygroundTimeline
                                            visible={visible}
                                            phase={phase}
                                            agent={agent}
                                            tool={clientTool}
                                        />
                                    </div>
                                </Card>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
