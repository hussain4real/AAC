/* ============================================================
   MAAC — Create Agent Wizard
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    Badge,
    Btn,
    Card,
    ExecChip,
    Field,
    Input,
    KV,
    PageHeader,
    Select,
    SensBadge,
    Textarea,
    Toggle,
} from '@/components/maac/ui';
import type { Application, Llm, Project, Tool } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ── Wizard data shape ─────────────────────────────────────── */
interface AgentDraft {
    name: string;
    desc: string;
    app: string;
    project: string;
    prompt: string;
    llm: string;
    tools: string[];
    temp: number;
    maxTokens: number;
    guardrails: boolean;
    approval: boolean;
    masking: boolean;
}

/* ── Shared step prop types ────────────────────────────────── */
type SetFn = <K extends keyof AgentDraft>(k: K, v: AgentDraft[K]) => void;

interface StepProps {
    data: AgentDraft;
    set: SetFn;
}

interface StepReviewProps {
    data: AgentDraft;
    go: (name: 'sdk') => void;
}

/* ── StepHeader ────────────────────────────────────────────── */
function StepHeader({ title, sub }: { title: string; sub: string }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 17, fontWeight: 700 }}>{title}</div>
            <div style={{ fontSize: 13, color: 'var(--text-3)', marginTop: 3 }}>
                {sub}
            </div>
        </div>
    );
}

/* ── StepBasic ─────────────────────────────────────────────── */
function StepBasic({ data, set }: StepProps) {
    const { scope } = useMaacNav();
    const MAAC = useMaacData();
    const apps: Application[] = scope.apps.length ? scope.apps : MAAC.apps;
    const projs: Project[] = scope.projects.filter((p) => p.appId === data.app);

    return (
        <div>
            <StepHeader
                title="Basic information"
                sub="Name your agent and choose where it lives."
            />
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                    maxWidth: 560,
                }}
            >
                <Field label="Agent name" required>
                    <Input
                        value={data.name}
                        onChange={(e) => set('name', e.target.value)}
                        placeholder="e.g. Operations Summary Agent"
                    />
                </Field>
                <Field
                    label="Short description"
                    hint="One line shown in lists and cards."
                >
                    <Input
                        value={data.desc}
                        onChange={(e) => set('desc', e.target.value)}
                        placeholder="What does this agent do?"
                    />
                </Field>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Application" required>
                        <Select
                            value={data.app}
                            onChange={(v) => set('app', v)}
                            options={apps.map((a) => ({
                                value: a.id,
                                label: a.name,
                            }))}
                        />
                    </Field>
                    <Field label="Project" required>
                        <Select
                            value={data.project}
                            onChange={(v) => set('project', v)}
                            options={(projs.length
                                ? projs
                                : MAAC.projectsByApp(data.app)
                            ).map((p) => ({ value: p.id, label: p.name }))}
                        />
                    </Field>
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        padding: '12px 14px',
                        background: 'var(--surface-2)',
                        borderRadius: 'var(--r-md)',
                        border: '1px solid var(--border)',
                    }}
                >
                    <Icon
                        name="info"
                        size={17}
                        style={{ color: 'var(--primary)', flexShrink: 0 }}
                    />
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                        }}
                    >
                        The agent inherits allowed models and tools from its
                        project. Client-side tools you attach must be
                        implemented by <b>{MAAC.appById(data.app)?.name}</b>.
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ── StepPrompt ────────────────────────────────────────────── */
function StepPrompt({ data, set }: StepProps) {
    return (
        <div>
            <StepHeader
                title="System prompt / use case"
                sub="Define the agent's role, boundaries, and expected behavior."
            />
            <Field
                label="System prompt"
                required
                hint={`${data.prompt.length} characters · ~${Math.round(data.prompt.length / 4)} tokens`}
            >
                <Textarea
                    value={data.prompt}
                    onChange={(e) => set('prompt', e.target.value)}
                    rows={11}
                    style={{
                        fontFamily: 'var(--mono)',
                        fontSize: 12.5,
                        lineHeight: 1.6,
                    }}
                />
            </Field>
            <div
                style={{
                    display: 'flex',
                    gap: 8,
                    marginTop: 12,
                    flexWrap: 'wrap',
                }}
            >
                <span
                    style={{
                        fontSize: 11.5,
                        color: 'var(--text-3)',
                        fontWeight: 600,
                        alignSelf: 'center',
                    }}
                >
                    Templates:
                </span>
                {(
                    ['Summarizer', 'Analyst', 'Reviewer', 'Assistant'] as const
                ).map((t) => (
                    <Badge
                        key={t}
                        tone="purple"
                        soft
                        style={{ cursor: 'pointer' }}
                    >
                        {t}
                    </Badge>
                ))}
            </div>
        </div>
    );
}

/* ── StepLLM ───────────────────────────────────────────────── */
function StepLLM({ data, set }: StepProps) {
    const MAAC = useMaacData();

    return (
        <div>
            <StepHeader
                title="Select LLM"
                sub="Choose from the company-approved model catalog. Availability may be restricted by project."
            />
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 12,
                }}
            >
                {MAAC.llms
                    .filter((l: Llm) => l.status === 'Approved')
                    .map((l: Llm) => {
                        const on = data.llm === l.id;

                        return (
                            <div
                                key={l.id}
                                onClick={() => set('llm', l.id)}
                                style={{
                                    cursor: 'pointer',
                                    padding: '14px 15px',
                                    borderRadius: 'var(--r-lg)',
                                    border: `1.5px solid ${on ? 'var(--primary)' : 'var(--border)'}`,
                                    background: on
                                        ? 'var(--primary-soft)'
                                        : 'var(--surface)',
                                    transition: 'all .12s',
                                    position: 'relative',
                                }}
                            >
                                {on && (
                                    <span
                                        style={{
                                            position: 'absolute',
                                            top: 12,
                                            right: 12,
                                            width: 20,
                                            height: 20,
                                            borderRadius: 999,
                                            background: 'var(--primary)',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Icon
                                            name="check"
                                            size={13}
                                            strokeWidth={3}
                                        />
                                    </span>
                                )}
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 9,
                                        marginBottom: 8,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 30,
                                            height: 30,
                                            borderRadius: 8,
                                            background: 'var(--navy-900)',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Icon name="llm" size={16} />
                                    </span>
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {l.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11,
                                                color: 'var(--text-3)',
                                            }}
                                        >
                                            {l.provider}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-2)',
                                        lineHeight: 1.45,
                                        marginBottom: 10,
                                        minHeight: 34,
                                    }}
                                >
                                    {l.note}
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        gap: 6,
                                    }}
                                >
                                    <Badge tone="neutral">Ctx {l.ctx}</Badge>
                                    <Badge tone="neutral">
                                        ${l.inCost}/{l.outCost} per 1M
                                    </Badge>
                                    <SensBadge level={l.sensitivity} />
                                </div>
                            </div>
                        );
                    })}
            </div>
        </div>
    );
}

/* ── StepTools ─────────────────────────────────────────────── */
function StepTools({ data, set }: StepProps) {
    const MAAC = useMaacData();
    const groups: { title: string; scope: Tool['scope']; desc: string }[] = [
        {
            title: 'Global Tools',
            scope: 'Global',
            desc: 'Shared across projects, subject to platform policy.',
        },
        {
            title: 'Project Tools',
            scope: 'Project',
            desc: 'Available to agents within this project.',
        },
        {
            title: 'Agent Tools',
            scope: 'Agent',
            desc: 'Specific to this agent only.',
        },
    ];
    const toggle = (id: string) =>
        set(
            'tools',
            data.tools.includes(id)
                ? data.tools.filter((t) => t !== id)
                : [...data.tools, id],
        );

    return (
        <div>
            <StepHeader
                title="Select tools"
                sub="Attach tools the agent can call. Client-side tools require SDK implementation by the owning application."
            />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                {groups.map((g) => {
                    const ts: Tool[] = MAAC.tools.filter(
                        (t: Tool) => t.scope === g.scope,
                    );

                    if (!ts.length) {
                        return null;
                    }

                    return (
                        <div key={g.scope}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'baseline',
                                    gap: 8,
                                    marginBottom: 9,
                                }}
                            >
                                <span
                                    style={{ fontSize: 12.5, fontWeight: 700 }}
                                >
                                    {g.title}
                                </span>
                                <span
                                    style={{
                                        fontSize: 11.5,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    {g.desc}
                                </span>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 7,
                                }}
                            >
                                {ts.map((t) => {
                                    const on = data.tools.includes(t.id);

                                    return (
                                        <div
                                            key={t.id}
                                            onClick={() => toggle(t.id)}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 12,
                                                padding: '10px 13px',
                                                borderRadius: 'var(--r-md)',
                                                border: `1.5px solid ${on ? 'var(--primary)' : 'var(--border)'}`,
                                                background: on
                                                    ? 'var(--primary-soft)'
                                                    : 'var(--surface)',
                                                cursor: 'pointer',
                                                transition: 'all .12s',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 18,
                                                    height: 18,
                                                    borderRadius: 5,
                                                    flexShrink: 0,
                                                    border: on
                                                        ? 'none'
                                                        : '1.5px solid var(--border-2)',
                                                    background: on
                                                        ? 'var(--primary)'
                                                        : 'transparent',
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                }}
                                            >
                                                {on && (
                                                    <Icon
                                                        name="check"
                                                        size={12}
                                                        strokeWidth={3}
                                                    />
                                                )}
                                            </span>
                                            <div
                                                style={{ flex: 1, minWidth: 0 }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 8,
                                                    }}
                                                >
                                                    <span
                                                        className="mono"
                                                        style={{
                                                            fontSize: 12.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {t.name}
                                                    </span>
                                                    <ExecChip
                                                        mode={t.execMode}
                                                    />
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: 'var(--text-3)',
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {t.desc}
                                                </div>
                                            </div>
                                            {t.execMode === 'client' &&
                                                t.impl !== 'implemented' && (
                                                    <Badge tone="orange" dot>
                                                        Needs SDK
                                                    </Badge>
                                                )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
            <div
                style={{ marginTop: 14, fontSize: 12, color: 'var(--text-3)' }}
            >
                <b style={{ color: 'var(--text)' }}>{data.tools.length}</b>{' '}
                tools selected
            </div>
        </div>
    );
}

/* ── StepRuntime ───────────────────────────────────────────── */
function StepRuntime({ data, set }: StepProps) {
    const guards: {
        k: 'guardrails' | 'approval' | 'masking';
        label: string;
        desc: string;
    }[] = [
        {
            k: 'guardrails',
            label: 'Prompt & tool-call guardrails',
            desc: 'Screen prompts and tool arguments for policy violations.',
        },
        {
            k: 'approval',
            label: 'Require approval before production',
            desc: 'Owner must approve before this agent runs in Production.',
        },
        {
            k: 'masking',
            label: 'Mask sensitive tool results in logs',
            desc: 'Restricted & Confidential outputs are masked in run logs.',
        },
    ];

    return (
        <div>
            <StepHeader
                title="Runtime & safety settings"
                sub="Tune generation behavior and enforce governance controls."
            />
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 18,
                    maxWidth: 600,
                    marginBottom: 22,
                }}
            >
                <Field
                    label={`Temperature — ${data.temp}`}
                    hint="Lower = more deterministic"
                >
                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.1"
                        value={data.temp}
                        onChange={(e) =>
                            set('temp', parseFloat(e.target.value))
                        }
                        style={{ width: '100%', accentColor: 'var(--primary)' }}
                    />
                </Field>
                <Field label="Max output tokens">
                    <Select
                        value={String(data.maxTokens)}
                        onChange={(v) => set('maxTokens', parseInt(v))}
                        options={['800', '1200', '1500', '1800', '2400']}
                    />
                </Field>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column' }}>
                {guards.map((g, i) => (
                    <div
                        key={g.k}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 14,
                            padding: '13px 0',
                            borderTop: i ? '1px solid var(--border)' : 'none',
                        }}
                    >
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 13, fontWeight: 600 }}>
                                {g.label}
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-3)',
                                    marginTop: 2,
                                }}
                            >
                                {g.desc}
                            </div>
                        </div>
                        <Toggle on={data[g.k]} onChange={(v) => set(g.k, v)} />
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ── StepReview ────────────────────────────────────────────── */
function StepReview({ data, go }: StepReviewProps) {
    const MAAC = useMaacData();
    const llm: Llm | undefined = MAAC.llmById(data.llm);
    const app: Application | undefined = MAAC.appById(data.app);
    const proj: Project | undefined = MAAC.projectById(data.project);
    const clientTools: Tool[] = data.tools
        .map((t) => MAAC.toolById(t))
        .filter((t): t is Tool => t?.execMode === 'client');
    const missing: Tool[] = clientTools.filter((t) => t.impl !== 'implemented');

    return (
        <div>
            <StepHeader
                title="Review & publish"
                sub="Confirm the configuration. The agent starts in Draft until published."
            />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <div
                    style={{
                        padding: '14px 16px',
                        borderRadius: 'var(--r-lg)',
                        border: '1px solid var(--border)',
                        background: 'var(--surface-2)',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 11,
                            marginBottom: 12,
                        }}
                    >
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
                            }}
                        >
                            <Icon name="agents" size={19} />
                        </span>
                        <div>
                            <div style={{ fontSize: 15, fontWeight: 700 }}>
                                {data.name || 'Untitled Agent'}
                            </div>
                            <div
                                style={{ fontSize: 12, color: 'var(--text-3)' }}
                            >
                                {data.desc || 'No description'}
                            </div>
                        </div>
                    </div>
                    <KV
                        cols={3}
                        items={[
                            { k: 'Application', v: app?.name },
                            { k: 'Project', v: proj?.name },
                            { k: 'Model', v: llm?.name },
                            { k: 'Tools', v: `${data.tools.length} attached` },
                            { k: 'Temperature', v: data.temp, mono: true },
                            { k: 'Max tokens', v: data.maxTokens, mono: true },
                        ]}
                    />
                </div>
                {missing.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 11,
                            padding: '13px 15px',
                            background: 'var(--orange-100)',
                            borderRadius: 'var(--r-md)',
                            border: '1px solid var(--orange-400)',
                        }}
                    >
                        <Icon
                            name="alert"
                            size={18}
                            style={{
                                color: 'var(--orange-600)',
                                flexShrink: 0,
                            }}
                        />
                        <div
                            style={{
                                fontSize: 12.5,
                                color: 'var(--text-2)',
                                lineHeight: 1.5,
                            }}
                        >
                            <b>
                                {missing.length} client-side tool
                                {missing.length > 1 ? 's' : ''}
                            </b>{' '}
                            still need implementation in <b>{app?.name}</b>{' '}
                            before this agent can run in production:{' '}
                            {missing.map((t) => (
                                <span
                                    key={t.id}
                                    className="mono"
                                    style={{ color: 'var(--orange-600)' }}
                                >
                                    {t.name}{' '}
                                </span>
                            ))}
                            <div style={{ marginTop: 8 }}>
                                <Btn
                                    variant="default"
                                    size="sm"
                                    iconRight="arrowRight"
                                    onClick={() => go('sdk')}
                                >
                                    Open SDK Implementation Center
                                </Btn>
                            </div>
                        </div>
                    </div>
                )}
                <div
                    style={{
                        display: 'flex',
                        gap: 11,
                        padding: '13px 15px',
                        background: 'var(--teal-100)',
                        borderRadius: 'var(--r-md)',
                        border: '1px solid var(--teal-300)',
                    }}
                >
                    <Icon
                        name="shield"
                        size={18}
                        style={{ color: 'var(--teal-600)', flexShrink: 0 }}
                    />
                    <div
                        style={{
                            fontSize: 12.5,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                        }}
                    >
                        Guardrails {data.guardrails ? 'enabled' : 'disabled'} ·
                        Production approval{' '}
                        {data.approval ? 'required' : 'skipped'} · Sensitive
                        logging {data.masking ? 'masked' : 'stored'}.
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ── CreateAgent (page) ────────────────────────────────────── */
export default function CreateAgent() {
    const { go, back } = useMaacNav();
    const [step, setStep] = useState(0);
    const [data, setData] = useState<AgentDraft>({
        name: '',
        desc: '',
        app: 'MOP',
        project: 'prj_mop_ops',
        prompt: 'You are a helpful AI agent for Milaha. Ground every statement in the data returned by your tools. Be concise, factual, and flag anything that requires human attention.',
        llm: 'gpt-4o',
        tools: ['searchPolicyDocuments'],
        temp: 0.3,
        maxTokens: 1500,
        guardrails: true,
        approval: true,
        masking: true,
    });
    const set: SetFn = (k, v) => setData((d) => ({ ...d, [k]: v }));

    const steps: { id: string; label: string; icon: string }[] = [
        { id: 'basic', label: 'Basic Information', icon: 'info' },
        { id: 'prompt', label: 'System Prompt', icon: 'doc' },
        { id: 'llm', label: 'Select LLM', icon: 'llm' },
        { id: 'tools', label: 'Select Tools', icon: 'tools' },
        { id: 'runtime', label: 'Runtime & Safety', icon: 'shield' },
        { id: 'review', label: 'Review & Publish', icon: 'check2' },
    ];
    const last = steps.length - 1;
    const canNext = step === 0 ? data.name.trim().length > 2 : true;

    return (
        <>
            <Head title="Create Agent" />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        { label: 'Agents', onClick: () => go('agents') },
                        { label: 'Create Agent' },
                    ]}
                    title="Create Agent"
                    sub="Configure a new AI agent in six steps. Simple enough for developers, clear enough for stakeholders."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '230px 1fr',
                        gap: 20,
                    }}
                >
                    {/* stepper */}
                    <div>
                        <div
                            style={{
                                position: 'sticky',
                                top: 0,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 2,
                            }}
                        >
                            {steps.map((s, i) => {
                                const done = i < step;
                                const on = i === step;

                                return (
                                    <button
                                        key={s.id}
                                        onClick={() => i <= step && setStep(i)}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '10px 12px',
                                            borderRadius: 9,
                                            border: 'none',
                                            cursor:
                                                i <= step
                                                    ? 'pointer'
                                                    : 'default',
                                            textAlign: 'left',
                                            background: on
                                                ? 'var(--primary-soft)'
                                                : 'transparent',
                                            transition: 'background .12s',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 26,
                                                height: 26,
                                                borderRadius: 999,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                flexShrink: 0,
                                                fontSize: 12,
                                                fontWeight: 700,
                                                background: done
                                                    ? 'var(--teal-500)'
                                                    : on
                                                      ? 'var(--primary)'
                                                      : 'var(--surface-3)',
                                                color:
                                                    done || on
                                                        ? '#fff'
                                                        : 'var(--text-3)',
                                                border:
                                                    !done && !on
                                                        ? '1px solid var(--border-2)'
                                                        : 'none',
                                            }}
                                        >
                                            {done ? (
                                                <Icon
                                                    name="check"
                                                    size={14}
                                                    strokeWidth={3}
                                                />
                                            ) : (
                                                i + 1
                                            )}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                fontWeight: on ? 700 : 500,
                                                color: on
                                                    ? 'var(--primary)'
                                                    : done
                                                      ? 'var(--text)'
                                                      : 'var(--text-3)',
                                            }}
                                        >
                                            {s.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* panel */}
                    <div>
                        <Card
                            style={{
                                minHeight: 430,
                                display: 'flex',
                                flexDirection: 'column',
                            }}
                        >
                            <div style={{ flex: 1 }}>
                                {step === 0 && (
                                    <StepBasic data={data} set={set} />
                                )}
                                {step === 1 && (
                                    <StepPrompt data={data} set={set} />
                                )}
                                {step === 2 && (
                                    <StepLLM data={data} set={set} />
                                )}
                                {step === 3 && (
                                    <StepTools data={data} set={set} />
                                )}
                                {step === 4 && (
                                    <StepRuntime data={data} set={set} />
                                )}
                                {step === 5 && (
                                    <StepReview data={data} go={go} />
                                )}
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    marginTop: 20,
                                    paddingTop: 16,
                                    borderTop: '1px solid var(--border)',
                                }}
                            >
                                <Btn
                                    variant="ghost"
                                    icon="chevleft"
                                    onClick={() =>
                                        step === 0 ? back() : setStep(step - 1)
                                    }
                                >
                                    {step === 0 ? 'Cancel' : 'Back'}
                                </Btn>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    Step {step + 1} of {steps.length}
                                </div>
                                {step < last ? (
                                    <Btn
                                        variant="primary"
                                        iconRight="chevright"
                                        disabled={!canNext}
                                        onClick={() => setStep(step + 1)}
                                    >
                                        Continue
                                    </Btn>
                                ) : (
                                    <Btn
                                        variant="primary"
                                        icon="check2"
                                        onClick={() => go('agents')}
                                    >
                                        Create & Publish Agent
                                    </Btn>
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
