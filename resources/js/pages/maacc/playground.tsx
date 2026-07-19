/* ============================================================
   MAACC — Agent Playground (real runtime)
   Runs a published agent against the live runtime (the same
   AgentRunner the SDK uses) via a console-authenticated endpoint.
   Response, trace, tokens, and latency are from the real run; cost is
   an estimate (token usage × the per-1M model price catalog, since
   providers return usage not cost). When the model calls a client-side
   tool, MAACC pauses and the console submits the result to resume.
   ============================================================ */
import type { FormDataConvertible } from '@inertiajs/core';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Lbl } from '@/components/maacc/common';
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
    Input,
    PageHeader,
    SectionHeader,
    Select,
    Textarea,
} from '@/components/maacc/ui';
import { usePlaygroundRun } from '@/hooks/use-playground-run';
import type {
    PlaygroundEnvironment,
    PlaygroundRunResult,
    PlaygroundToolCall,
    PlaygroundTraceEntry,
} from '@/hooks/use-playground-run';
import type {
    Agent,
    Environment as ConsoleEnvironment,
    Llm,
} from '@/maacc/data';
import { formatCurrency } from '@/maacc/format';
import { Icon } from '@/maacc/icons';
import type { IconName } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';
import type { MaaccData } from '@/maacc/use-data';
import type { MaaccProviderHealth, MaaccRoutingPolicy } from '@/types/global';

/* ── trace presentation ── */

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

const DEFAULT_MESSAGES: Record<string, string> = {
    ag_ops_summary:
        "Summarize today's vessel operations and flag any delays over 6 hours.",
    ag_approval_review:
        'Review my pending approvals and recommend an action for each.',
    ag_procure_insight:
        'Which suppliers had the most delayed deliveries last quarter?',
    ag_customer_trend: 'What are the top emerging complaint themes this week?',
};

const FALLBACK_MESSAGE =
    'Summarize the latest activity and flag anything that needs attention.';

/* ── client-side tool result helpers ── */

/**
 * Build a schema-shaped sample result for a client-side tool so the console
 * user can edit a realistic payload before submitting it back to the runtime.
 */
function sampleResult(
    schema: Record<string, string> | null,
): Record<string, FormDataConvertible> {
    const out: Record<string, FormDataConvertible> = {};

    if (!schema) {
        return out;
    }

    for (const [field, definition] of Object.entries(schema)) {
        const base = definition.replace('?', '').trim();
        out[field] =
            base === 'number' || base === 'integer'
                ? 0
                : base === 'boolean'
                  ? true
                  : base === 'array'
                    ? []
                    : base === 'object'
                      ? {}
                      : 'sample value';
    }

    return out;
}

/* ── local sub-components ── */

interface TraceTimelineProps {
    trace: PlaygroundTraceEntry[];
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
                                        minHeight: 14,
                                    }}
                                />
                            )}
                        </div>
                        <div
                            style={{
                                paddingBottom: i < trace.length - 1 ? 13 : 0,
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
                                    style={{ fontSize: 12.5, fontWeight: 600 }}
                                >
                                    {event.label}
                                </span>
                                {event.occurredAt && (
                                    <span
                                        className="mono"
                                        style={{
                                            fontSize: 10.5,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {event.occurredAt}
                                    </span>
                                )}
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--text-3)',
                                    marginTop: 2,
                                }}
                            >
                                {event.message}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

interface ClientToolPanelProps {
    toolCall: PlaygroundToolCall;
    onSubmit: (result: Record<string, FormDataConvertible>) => void;
    processing: boolean;
}

/**
 * Mounted with `key={toolCall.id}` so its editable result is initialized fresh
 * (from the tool's output schema) for each new client-side tool call.
 */
function ClientToolPanel({
    toolCall,
    onSubmit,
    processing,
}: ClientToolPanelProps) {
    const [value, setValue] = useState(() =>
        JSON.stringify(sampleResult(toolCall.output_schema), null, 2),
    );
    const [parseError, setParseError] = useState<string | null>(null);

    const submit = () => {
        let parsed: Record<string, FormDataConvertible>;

        try {
            parsed = JSON.parse(value) as Record<string, FormDataConvertible>;
        } catch {
            setParseError('The tool result must be valid JSON.');

            return;
        }

        setParseError(null);
        onSubmit(parsed);
    };

    return (
        <Card
            pad={false}
            style={{
                overflow: 'hidden',
                borderColor: 'var(--orange-400)',
                borderWidth: 1.5,
                animation: 'fadeUp .35s ease both',
            }}
        >
            <div
                style={{
                    padding: '13px 16px',
                    background:
                        'linear-gradient(100deg, var(--orange-100), transparent)',
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
                        background: 'var(--orange-600)',
                        color: '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                        animation: 'pulseDot 1.8s infinite',
                    }}
                >
                    <Icon name="link" size={19} />
                </span>
                <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 700 }}>
                        Client-Side Tool Execution Required
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            marginTop: 1,
                        }}
                    >
                        MAACC paused the run. The model requested{' '}
                        <b className="mono">{toolCall.tool}</b> — return its
                        result to resume.
                    </div>
                </div>
                <Badge tone="orange" dot>
                    Waiting for result
                </Badge>
            </div>

            <div style={{ padding: '15px 16px' }}>
                <div style={{ display: 'flex', gap: 6, marginBottom: 12 }}>
                    <ExecChip mode="client" />
                </div>

                <Lbl>Tool arguments (from the model)</Lbl>
                <CodeBlock
                    code={JSON.stringify(toolCall.arguments, null, 2)}
                    lang="json"
                    copyable={false}
                    style={{ marginBottom: 14 }}
                />

                <Lbl>Tool result (the application would compute this)</Lbl>
                <Textarea
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    rows={6}
                    style={{
                        fontFamily: 'var(--font-mono, monospace)',
                        fontSize: 12,
                    }}
                />
                {parseError && (
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--red-500)',
                            marginTop: 6,
                        }}
                    >
                        {parseError}
                    </div>
                )}

                <Btn
                    variant="primary"
                    icon="send"
                    onClick={submit}
                    disabled={processing}
                    style={{ marginTop: 12 }}
                >
                    {processing ? 'Submitting…' : 'Submit tool result & resume'}
                </Btn>
            </div>
        </Card>
    );
}

interface RunStatsProps {
    run: PlaygroundRunResult;
}

function RunStats({ run }: RunStatsProps) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 14,
                marginTop: 8,
                fontSize: 11,
                color: 'var(--text-3)',
                flexWrap: 'wrap',
            }}
        >
            <span className="mono">
                ⬆ {run.usage.tokens_in} in · ⬇ {run.usage.tokens_out} out
            </span>
            <span
                className="mono"
                title="Estimated: token usage × the model's per-1M price catalog. Providers return usage, not cost, via their API."
            >
                ~{formatCurrency(run.cost, run.currency)} est.
            </span>
            {run.latency_ms !== null && (
                <span className="mono">
                    ⏱ {(run.latency_ms / 1000).toFixed(2)}s
                </span>
            )}
            <span className="mono">{run.model}</span>
        </div>
    );
}

/* ── page ── */

function toRuntimeEnvironment(
    environment: ConsoleEnvironment,
): PlaygroundEnvironment {
    return environment.toLowerCase() as PlaygroundEnvironment;
}

function sameProvider(provider: Llm, id: string | null | undefined): boolean {
    return (
        id !== undefined &&
        id !== null &&
        (provider.id === id || provider.uuid === id)
    );
}

function sameAgent(agent: Agent, id: string): boolean {
    return agent.id === id || agent.uuid === id;
}

function providerHealthFor(
    provider: Llm,
    health: MaaccProviderHealth[],
): MaaccProviderHealth | undefined {
    return health.find((entry) => sameProvider(provider, entry.id));
}

function providerIsAvailable(
    provider: Llm | undefined,
    env: ConsoleEnvironment,
): boolean {
    return provider?.status === 'Approved' && provider.envs.includes(env);
}

function providerIsEligibleForPolicy(
    provider: Llm,
    policy: MaaccRoutingPolicy,
    health: MaaccProviderHealth[],
    env: ConsoleEnvironment,
): boolean {
    if (!providerIsAvailable(provider, env)) {
        return false;
    }

    if (
        policy.maxCostPer1k !== null &&
        provider.inCost + provider.outCost > policy.maxCostPer1k
    ) {
        return false;
    }

    const providerHealth = providerHealthFor(provider, health);

    if (providerHealth !== undefined && !providerHealth.healthy) {
        return false;
    }

    return !(
        policy.maxLatencyMs !== null &&
        providerHealth?.avgLatencyMs !== null &&
        providerHealth?.avgLatencyMs !== undefined &&
        providerHealth.avgLatencyMs > policy.maxLatencyMs
    );
}

function routingCandidates(
    policy: MaaccRoutingPolicy,
    agent: Agent,
    MAACC: MaaccData,
): Llm[] {
    const candidateIds = [
        policy.primaryProviderId ?? agent.llm,
        ...policy.fallbackProviderIds,
    ];

    return candidateIds
        .map((id) => MAACC.llms.find((provider) => sameProvider(provider, id)))
        .filter((provider): provider is Llm => provider !== undefined);
}

function hasEligibleRoutingProvider(
    agent: Agent | undefined,
    env: ConsoleEnvironment,
    MAACC: MaaccData,
): boolean {
    if (!agent) {
        return false;
    }

    return MAACC.routingPolicies.some((policy) => {
        if (!policy.enabled || !sameAgent(agent, policy.agentId)) {
            return false;
        }

        return routingCandidates(policy, agent, MAACC).some((provider) =>
            providerIsEligibleForPolicy(
                provider,
                policy,
                MAACC.providerHealth,
                env,
            ),
        );
    });
}

export default function Playground() {
    const { go, scope, env, setEnv } = useMaaccNav();
    const MAACC = useMaaccData();
    const { run, error, processing, start, submitToolResult, reset } =
        usePlaygroundRun();

    const environmentValue = toRuntimeEnvironment(env);
    const environmentProjects = useMemo(
        () => scope.projects.filter((project) => project.env === env),
        [env, scope.projects],
    );
    const environmentApps = useMemo(() => {
        const projectAppIds = new Set(
            environmentProjects.map((project) => project.appId),
        );

        return scope.apps.filter(
            (app) => app.env === env || projectAppIds.has(app.id),
        );
    }, [env, environmentProjects, scope.apps]);
    const environmentAgents = useMemo(() => {
        const appIds = new Set(environmentApps.map((app) => app.id));
        const projectIds = new Set(
            environmentProjects.map((project) => project.id),
        );

        return scope.agents.filter(
            (agent) =>
                appIds.has(agent.appId) && projectIds.has(agent.projectId),
        );
    }, [environmentApps, environmentProjects, scope.agents]);

    const agentParam = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    ).get('agent');
    const ag0 =
        (agentParam
            ? environmentAgents.find((agent) => agent.id === agentParam)
            : undefined) ||
        environmentAgents.find((agent) => agent.id === 'ag_ops_summary') ||
        environmentAgents[0];

    const [selectedAppId, setSelectedAppId] = useState(
        ag0?.appId ?? environmentApps[0]?.id ?? '',
    );
    const [selectedProjectId, setSelectedProjectId] = useState(
        ag0?.projectId ?? '',
    );
    const [selectedAgentId, setSelectedAgentId] = useState(ag0?.id ?? '');
    const appId = environmentApps.some(
        (candidate) => candidate.id === selectedAppId,
    )
        ? selectedAppId
        : (ag0?.appId ?? environmentApps[0]?.id ?? '');
    const projectOptions = useMemo(
        () => environmentProjects.filter((project) => project.appId === appId),
        [appId, environmentProjects],
    );
    const projectId = projectOptions.some(
        (candidate) => candidate.id === selectedProjectId,
    )
        ? selectedProjectId
        : ag0?.appId === appId &&
            projectOptions.some((candidate) => candidate.id === ag0.projectId)
          ? ag0.projectId
          : (projectOptions[0]?.id ?? '');
    const agentOptions = useMemo(
        () =>
            environmentAgents.filter(
                (candidate) =>
                    candidate.appId === appId &&
                    candidate.projectId === projectId,
            ),
        [appId, environmentAgents, projectId],
    );
    const agentId = agentOptions.some(
        (candidate) => candidate.id === selectedAgentId,
    )
        ? selectedAgentId
        : ag0?.projectId === projectId &&
            agentOptions.some((candidate) => candidate.id === ag0.id)
          ? ag0.id
          : (agentOptions[0]?.id ?? '');
    const agent = agentId ? MAACC.agentById(agentId) : undefined;

    const [msg, setMsg] = useState(
        DEFAULT_MESSAGES[selectedAgentId] || FALLBACK_MESSAGE,
    );

    const toolCall =
        run?.status === 'waiting_for_client' ? (run.tool_call ?? null) : null;

    const selectedLlm = agent ? MAACC.llmById(agent.llm) : undefined;
    const isPublished = agent?.status === 'Published';
    const modelAvailable = providerIsAvailable(selectedLlm, env);
    const routedModelAvailable = hasEligibleRoutingProvider(agent, env, MAACC);
    const runBlockReason = !agent
        ? `No agent is available in ${env}.`
        : !isPublished
          ? 'Publish this agent to run it from the console.'
          : !modelAvailable && !routedModelAvailable
            ? `${selectedLlm?.name ?? 'The selected model'} is not approved for ${env}.`
            : null;
    const canRun = runBlockReason === null && msg.trim() !== '';

    const onAgentChange = (id: string) => {
        const a = environmentAgents.find((candidate) => candidate.id === id);

        if (!a) {
            return;
        }

        setSelectedAgentId(id);
        setSelectedAppId(a.appId);
        setSelectedProjectId(a.projectId);
        setMsg(DEFAULT_MESSAGES[id] || FALLBACK_MESSAGE);
        reset();
    };

    const onRun = () => {
        if (agent && canRun) {
            void start(agent.id, msg, environmentValue);
        }
    };

    const examples = useMemo(
        () => Object.values(DEFAULT_MESSAGES).slice(0, 3),
        [],
    );

    return (
        <>
            <Head title="Agent Playground" />
            <div className="route-anim">
                <PageHeader
                    title="Agent Playground"
                    sub="Run a published agent against the live runtime and watch the real model, tool, and trace flow — including the client-side tool pause-and-resume."
                    actions={
                        <>
                            {run && (
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
                                onClick={() => {
                                    if (agent) {
                                        go('agent', { id: agent.id });
                                    }
                                }}
                                disabled={!agent}
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
                                <Field
                                    label="Playground environment"
                                    hint="Applies only to this governed test run."
                                >
                                    <Select
                                        ariaLabel="Playground environment"
                                        value={env}
                                        onChange={(value) => {
                                            reset();
                                            setEnv(value as typeof env);
                                        }}
                                        options={[
                                            'Production',
                                            'Staging',
                                            'Development',
                                        ]}
                                    />
                                </Field>
                                <Field label="Application">
                                    {environmentApps.length > 0 ? (
                                        <Select
                                            ariaLabel="Playground application"
                                            value={appId}
                                            onChange={(value) => {
                                                const nextProject =
                                                    environmentProjects.find(
                                                        (candidate) =>
                                                            candidate.appId ===
                                                            value,
                                                    );
                                                const nextAgent =
                                                    environmentAgents.find(
                                                        (candidate) =>
                                                            candidate.appId ===
                                                                value &&
                                                            (!nextProject ||
                                                                candidate.projectId ===
                                                                    nextProject.id),
                                                    ) ||
                                                    environmentAgents.find(
                                                        (candidate) =>
                                                            candidate.appId ===
                                                            value,
                                                    );

                                                setSelectedAppId(value);

                                                if (nextAgent) {
                                                    onAgentChange(nextAgent.id);

                                                    return;
                                                }

                                                setSelectedProjectId(
                                                    nextProject?.id ?? '',
                                                );
                                                setSelectedAgentId('');
                                                setMsg(FALLBACK_MESSAGE);
                                                reset();
                                            }}
                                            options={environmentApps.map(
                                                (application) => ({
                                                    value: application.id,
                                                    label: application.name,
                                                }),
                                            )}
                                        />
                                    ) : (
                                        <Input
                                            value={`No ${env} applications`}
                                            readOnly
                                        />
                                    )}
                                </Field>
                                <Field label="Project">
                                    {projectOptions.length > 0 ? (
                                        <Select
                                            ariaLabel="Playground project"
                                            value={projectId}
                                            onChange={(value) => {
                                                const nextAgent =
                                                    environmentAgents.find(
                                                        (candidate) =>
                                                            candidate.projectId ===
                                                            value,
                                                    );

                                                setSelectedProjectId(value);

                                                if (nextAgent) {
                                                    onAgentChange(nextAgent.id);

                                                    return;
                                                }

                                                setSelectedAgentId('');
                                                setMsg(FALLBACK_MESSAGE);
                                                reset();
                                            }}
                                            options={projectOptions.map(
                                                (project) => ({
                                                    value: project.id,
                                                    label: project.name,
                                                }),
                                            )}
                                        />
                                    ) : (
                                        <Input
                                            value={`No ${env} projects`}
                                            readOnly
                                        />
                                    )}
                                </Field>
                                <Field label="Agent">
                                    {agentOptions.length > 0 ? (
                                        <Select
                                            ariaLabel="Playground agent"
                                            value={agentId}
                                            onChange={onAgentChange}
                                            options={agentOptions.map(
                                                (candidate) => ({
                                                    value: candidate.id,
                                                    label: candidate.name,
                                                }),
                                            )}
                                        />
                                    ) : (
                                        <Input
                                            value={`No ${env} agents`}
                                            readOnly
                                        />
                                    )}
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
                                    flexWrap: 'wrap',
                                }}
                            >
                                {agent ? (
                                    <>
                                        <Badge tone="purple" soft icon="llm">
                                            {selectedLlm?.name ?? agent.llm}
                                        </Badge>
                                        <Badge tone="neutral">
                                            {agent.tools.length} tools
                                        </Badge>
                                        <AgentBadge status={agent.status} />
                                    </>
                                ) : (
                                    <Badge tone="neutral">No {env} agent</Badge>
                                )}
                            </div>
                            {runBlockReason && (
                                <div
                                    style={{
                                        marginTop: 10,
                                        fontSize: 11.5,
                                        color: 'var(--orange-600)',
                                        display: 'flex',
                                        gap: 7,
                                        alignItems: 'center',
                                    }}
                                >
                                    <Icon name="alert" size={14} />{' '}
                                    {runBlockReason}
                                </div>
                            )}
                        </Card>

                        <Card>
                            <SectionHeader title="User message" icon="user" />
                            <Textarea
                                value={msg}
                                onChange={(e) => setMsg(e.target.value)}
                                rows={4}
                                placeholder="Enter a message to send to the agent…"
                                disabled={processing}
                            />
                            <Btn
                                variant="primary"
                                full
                                icon={processing ? 'refresh' : 'play'}
                                style={{ marginTop: 12 }}
                                disabled={processing || !canRun}
                                onClick={onRun}
                            >
                                {processing
                                    ? 'Running…'
                                    : run
                                      ? 'Run Again'
                                      : 'Run Agent'}
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
                                    {examples.map((m, i) => (
                                        <button
                                            key={i}
                                            onClick={() => {
                                                if (!processing) {
                                                    setMsg(m);
                                                }
                                            }}
                                            className="maacc-row"
                                            style={{
                                                textAlign: 'left',
                                                border: '1px solid var(--border)',
                                                background: 'var(--surface)',
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
                        {!run && !processing ? (
                            <Card style={{ minHeight: 480 }}>
                                <EmptyState
                                    icon="playground"
                                    title="Ready to run"
                                    desc="Configure a published agent and press Run. The console invokes the live runtime — the response, trace, and tokens are from the real run; cost is estimated from token usage × the model price catalog."
                                    action={
                                        <Btn
                                            variant="primary"
                                            icon="play"
                                            onClick={onRun}
                                            disabled={processing || !canRun}
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
                                        <Avatar name="You" size={32} />
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

                                    {processing && (
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                                fontSize: 12.5,
                                                color: 'var(--text-2)',
                                                padding: '4px 2px',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 22,
                                                    height: 22,
                                                    borderRadius: 999,
                                                    border: '2px solid var(--border)',
                                                    borderTopColor:
                                                        'var(--primary)',
                                                    animation:
                                                        'spin 0.7s linear infinite',
                                                }}
                                            />
                                            Invoking the runtime…
                                        </div>
                                    )}

                                    {run?.status === 'completed' && (
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
                                            <div
                                                style={{ flex: 1, minWidth: 0 }}
                                            >
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
                                                    {agent?.name ?? 'Agent'}
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
                                                        whiteSpace: 'pre-wrap',
                                                    }}
                                                >
                                                    {run.response}
                                                </div>
                                                <RunStats run={run} />
                                            </div>
                                        </div>
                                    )}

                                    {run &&
                                        (run.status === 'failed' ||
                                            run.status === 'expired' ||
                                            run.status === 'cancelled') && (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 10,
                                                    padding: '12px 14px',
                                                    background:
                                                        'var(--red-100)',
                                                    border: '1px solid var(--red-200, var(--red-100))',
                                                    borderRadius: 'var(--r-md)',
                                                    animation:
                                                        'fadeUp .4s ease both',
                                                }}
                                            >
                                                <Icon
                                                    name="xCircle"
                                                    size={18}
                                                />
                                                <div
                                                    style={{
                                                        flex: 1,
                                                        minWidth: 0,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        Run {run.status}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: 'var(--text-2)',
                                                            marginTop: 3,
                                                            whiteSpace:
                                                                'pre-wrap',
                                                        }}
                                                    >
                                                        {run.error}
                                                    </div>
                                                    <RunStats run={run} />
                                                </div>
                                            </div>
                                        )}

                                    {error && (
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: 'var(--red-500)',
                                                marginTop: 10,
                                            }}
                                        >
                                            {error}
                                        </div>
                                    )}
                                </Card>

                                {/* client tool pause/resume */}
                                {run && toolCall && (
                                    <ClientToolPanel
                                        key={toolCall.id}
                                        toolCall={toolCall}
                                        processing={processing}
                                        onSubmit={(parsed) =>
                                            void submitToolResult(
                                                run.run_id,
                                                toolCall.id,
                                                parsed,
                                            )
                                        }
                                    />
                                )}

                                {/* trace */}
                                {run && run.trace.length > 0 && (
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
                                                title="Execution trace"
                                                icon="runs"
                                                style={{ marginBottom: 0 }}
                                            />
                                            <Badge
                                                tone={
                                                    run.status === 'completed'
                                                        ? 'teal'
                                                        : run.status ===
                                                            'waiting_for_client'
                                                          ? 'orange'
                                                          : run.status ===
                                                              'failed'
                                                            ? 'red'
                                                            : 'blue'
                                                }
                                                dot
                                            >
                                                {run.status}
                                            </Badge>
                                        </div>
                                        <div
                                            style={{
                                                padding: '14px 16px 16px',
                                            }}
                                        >
                                            <TraceTimeline trace={run.trace} />
                                        </div>
                                    </Card>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
