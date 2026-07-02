/* ============================================================
   MAAC — Application Detail
   ============================================================ */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as archiveApplication,
    update as updateApplication,
} from '@/actions/App/Http/Controllers/Maac/ApplicationController';
import {
    revoke as revokeCredential,
    rotate as rotateCredential,
    store as storeCredential,
} from '@/actions/App/Http/Controllers/Maac/CredentialController';
import {
    AppHistory,
    NoAccess,
    PlaceholderScreen,
} from '@/components/maac/common';
import {
    APP_STATUS,
    AgentBadge,
    AppMark,
    Badge,
    Btn,
    Card,
    CodeBlock,
    EmptyState,
    EnvBadge,
    ExecChip,
    Field,
    ImplBadge,
    Input,
    KV,
    Modal,
    PageHeader,
    SectionHeader,
    Select,
    SensBadge,
    Table,
    Tabs,
    Td,
    Textarea,
    Tr,
} from '@/components/maac/ui';
import type {
    Agent,
    Application,
    Credential,
    Project,
    Tool,
} from '@/maac/data';
import {
    APP_STATUS_OPTIONS,
    ENV_OPTIONS,
    FieldError,
    toEnumValue,
    useCurrentTeam,
} from '@/maac/forms';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { MaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ---- Local sub-components ---- */

function StatusRow({
    label,
    ok,
    okText,
    badText,
    sub,
}: {
    label: string;
    ok: boolean;
    okText: string;
    badText: string;
    sub: string;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
            <span
                style={{
                    width: 30,
                    height: 30,
                    borderRadius: 8,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: ok ? 'var(--teal-100)' : 'var(--red-100)',
                    color: ok ? 'var(--teal-600)' : 'var(--red-600)',
                    flexShrink: 0,
                }}
            >
                <Icon name={ok ? 'check2' : 'alert'} size={16} />
            </span>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12.5, fontWeight: 600 }}>{label}</div>
                <div style={{ fontSize: 11, color: 'var(--text-3)' }}>
                    {sub}
                </div>
            </div>
            <Badge tone={ok ? 'teal' : 'red'} dot>
                {ok ? okText : badText}
            </Badge>
        </div>
    );
}

function MiniStat({
    label,
    value,
    icon,
    tone = 'purple',
}: {
    label: string;
    value: number;
    icon: string;
    tone?: string;
}) {
    const tones: Record<string, { bg: string; fg: string; bd: string }> = {
        purple: {
            bg: 'var(--purple-100)',
            fg: 'var(--purple-600)',
            bd: 'var(--purple-200)',
        },
        teal: {
            bg: 'var(--teal-100)',
            fg: 'var(--teal-600)',
            bd: 'var(--teal-300)',
        },
        blue: {
            bg: 'var(--blue-100)',
            fg: 'var(--blue-500)',
            bd: 'var(--blue-100)',
        },
        amber: {
            bg: 'var(--amber-100)',
            fg: 'var(--amber-500)',
            bd: 'var(--amber-100)',
        },
    };
    const t = tones[tone] ?? tones['purple'];

    return (
        <div
            style={{
                padding: '11px 12px',
                background: 'var(--surface-2)',
                borderRadius: 'var(--r-md)',
                border: '1px solid var(--border)',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 7,
                    color: t.fg,
                    marginBottom: 5,
                }}
            >
                <Icon name={icon} size={15} />
            </div>
            <div
                className="tnum"
                style={{ fontSize: 20, fontWeight: 700, lineHeight: 1 }}
            >
                {value}
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-3)', marginTop: 3 }}>
                {label}
            </div>
        </div>
    );
}

function AppOverview({
    app,
    agents,
    projects,
    tools,
    go,
    setTab,
}: {
    app: Application;
    agents: Agent[];
    projects: Project[];
    tools: Tool[];
    go: MaacNav['go'];
    setTab: (tab: string) => void;
}) {
    const MAAC = useMaacData();
    const missing = tools.filter((t) =>
        ['required', 'outdated', 'incompatible'].includes(t.impl),
    );
    const implemented = tools.filter((t) => t.impl === 'implemented');

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Application metadata" icon="info" />
                    <KV
                        cols={3}
                        items={[
                            { k: 'Application code', v: app.code, mono: true },
                            { k: 'Owning department', v: app.dept },
                            { k: 'Environment', v: <EnvBadge env={app.env} /> },
                            { k: 'Technical owner', v: app.owner },
                            { k: 'Owner email', v: app.ownerEmail, mono: true },
                            { k: 'Tech stack', v: app.stack },
                            { k: 'Region', v: app.region },
                            { k: 'Registered', v: app.created },
                            { k: 'Last connected', v: app.lastConnected },
                        ]}
                    />
                </Card>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Card>
                        <SectionHeader
                            title="Client-side tools implemented"
                            icon="check2"
                            right={
                                <Badge tone="teal">
                                    {implemented.length}/{tools.length}
                                </Badge>
                            }
                        />
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {implemented.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    None implemented yet.
                                </div>
                            )}
                            {implemented.map((t) => (
                                <div
                                    key={t.id}
                                    className="maac-row"
                                    onClick={() => go('tool', { id: t.id })}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '7px 9px',
                                        borderRadius: 7,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <span
                                        className="mono"
                                        style={{ fontSize: 12 }}
                                    >
                                        {t.name}
                                    </span>
                                    <Icon
                                        name="check"
                                        size={15}
                                        style={{ color: 'var(--teal-600)' }}
                                    />
                                </div>
                            ))}
                        </div>
                    </Card>
                    <Card
                        style={{
                            borderColor: missing.length
                                ? 'var(--orange-400)'
                                : 'var(--border)',
                        }}
                    >
                        <SectionHeader
                            title="Tools missing implementation"
                            icon="alert"
                            right={
                                <Badge tone="orange">{missing.length}</Badge>
                            }
                        />
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {missing.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    All tools implemented 🎉
                                </div>
                            )}
                            {missing.map((t) => (
                                <div
                                    key={t.id}
                                    className="maac-row"
                                    onClick={() => go('tool', { id: t.id })}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '7px 9px',
                                        borderRadius: 7,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <span
                                        className="mono"
                                        style={{ fontSize: 12 }}
                                    >
                                        {t.name}
                                    </span>
                                    <ImplBadge status={t.impl} />
                                </div>
                            ))}
                        </div>
                        {missing.length > 0 && (
                            <Btn
                                variant="soft"
                                size="sm"
                                full
                                style={{ marginTop: 12 }}
                                iconRight="arrowRight"
                                onClick={() => go('sdk')}
                            >
                                Open SDK Implementation Center
                            </Btn>
                        )}
                    </Card>
                </div>

                <Card pad={false}>
                    <div style={{ padding: '14px 16px 12px' }}>
                        <SectionHeader
                            title="Published agents"
                            icon="agents"
                            style={{ marginBottom: 0 }}
                            right={
                                <Btn
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setTab('agents')}
                                    iconRight="arrowRight"
                                >
                                    All agents
                                </Btn>
                            }
                        />
                    </div>
                    <Table
                        columns={[
                            { label: 'Agent' },
                            { label: 'LLM' },
                            { label: 'Status' },
                            { label: 'Success', align: 'right' },
                            { label: 'Runs 7d', align: 'right' },
                        ]}
                    >
                        {agents.slice(0, 4).map((a) => (
                            <Tr
                                key={a.id}
                                onClick={() => go('agent', { id: a.id })}
                            >
                                <Td strong>{a.name}</Td>
                                <Td mono>{MAAC.llmById(a.llm)?.name}</Td>
                                <Td>
                                    <AgentBadge status={a.status} />
                                </Td>
                                <Td align="right" mono>
                                    {a.successRate ? a.successRate + '%' : '—'}
                                </Td>
                                <Td align="right" mono>
                                    {a.runs7d.toLocaleString()}
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                </Card>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Connection status" icon="link" />
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 11,
                        }}
                    >
                        <StatusRow
                            label="SDK connection"
                            ok={app.status === 'Active'}
                            okText="Connected"
                            badText="Disconnected"
                            sub={`Last seen ${app.lastConnected}`}
                        />
                        <StatusRow
                            label="API credentials"
                            ok={app.credStatus === 'Active'}
                            okText="Active"
                            badText={app.credStatus}
                            sub="Client ID + Secret"
                        />
                        <StatusRow
                            label="Tool sync"
                            ok={app.toolsImplemented === app.toolsRequired}
                            okText="In sync"
                            badText={`${app.toolsRequired - app.toolsImplemented} pending`}
                            sub="Client-side handlers"
                        />
                    </div>
                </Card>
                <Card>
                    <SectionHeader title="At a glance" icon="grid" />
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 10,
                        }}
                    >
                        <MiniStat
                            label="Projects"
                            value={projects.length}
                            icon="projects"
                        />
                        <MiniStat
                            label="Agents"
                            value={agents.length}
                            icon="agents"
                        />
                        <MiniStat
                            label="Tools required"
                            value={app.toolsRequired}
                            icon="tools"
                        />
                        <MiniStat
                            label="Implemented"
                            value={app.toolsImplemented}
                            icon="check2"
                            tone="teal"
                        />
                    </div>
                </Card>
            </div>
        </div>
    );
}

function AppProjects({
    projects,
    go,
}: {
    projects: Project[];
    go: MaacNav['go'];
}) {
    return (
        <Table
            columns={[
                { label: 'Project' },
                { label: 'Environment' },
                { label: 'Status' },
                { label: 'Agents', align: 'right' },
                { label: 'Tools', align: 'right' },
                { label: 'Runs 7d', align: 'right' },
            ]}
        >
            {projects.map((p) => (
                <Tr key={p.id} onClick={() => go('projects')}>
                    <Td strong>
                        <div>
                            {p.name}
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--text-3)',
                                    fontWeight: 400,
                                }}
                            >
                                {p.desc}
                            </div>
                        </div>
                    </Td>
                    <Td>
                        <EnvBadge env={p.env} />
                    </Td>
                    <Td>
                        <Badge
                            tone={p.status === 'Active' ? 'teal' : 'neutral'}
                            dot
                        >
                            {p.status}
                        </Badge>
                    </Td>
                    <Td align="right" mono>
                        {p.agents}
                    </Td>
                    <Td align="right" mono>
                        {p.tools}
                    </Td>
                    <Td align="right" mono>
                        {p.runs7d.toLocaleString()}
                    </Td>
                </Tr>
            ))}
        </Table>
    );
}

function AppAgents({ agents, go }: { agents: Agent[]; go: MaacNav['go'] }) {
    const MAAC = useMaacData();

    return (
        <Table
            columns={[
                { label: 'Agent' },
                { label: 'LLM' },
                { label: 'Version' },
                { label: 'Status' },
                { label: 'Tools', align: 'right' },
                { label: 'Success', align: 'right' },
                { label: 'Last run', align: 'right' },
            ]}
        >
            {agents.map((a) => (
                <Tr key={a.id} onClick={() => go('agent', { id: a.id })}>
                    <Td strong>{a.name}</Td>
                    <Td mono>{MAAC.llmById(a.llm)?.name}</Td>
                    <Td mono>{a.version}</Td>
                    <Td>
                        <AgentBadge status={a.status} />
                    </Td>
                    <Td align="right" mono>
                        {a.tools.length}
                    </Td>
                    <Td align="right" mono>
                        {a.successRate ? a.successRate + '%' : '—'}
                    </Td>
                    <Td align="right" style={{ color: 'var(--text-3)' }}>
                        {a.lastRun}
                    </Td>
                </Tr>
            ))}
        </Table>
    );
}

function AppTools({ tools, go }: { tools: Tool[]; go: MaacNav['go'] }) {
    const MAAC = useMaacData();

    return (
        <Table
            columns={[
                { label: 'Tool' },
                { label: 'Execution mode' },
                { label: 'Sensitivity' },
                { label: 'Used by' },
                { label: 'Status' },
            ]}
        >
            {tools.map((t) => (
                <Tr key={t.id} onClick={() => go('tool', { id: t.id })}>
                    <Td strong mono>
                        {t.name}
                    </Td>
                    <Td>
                        <ExecChip mode={t.execMode} />
                    </Td>
                    <Td>
                        <SensBadge level={t.sensitivity} />
                    </Td>
                    <Td>
                        {t.usedBy
                            .map((u) =>
                                MAAC.agentById(u)?.name.replace(' Agent', ''),
                            )
                            .join(', ')}
                    </Td>
                    <Td>
                        <ImplBadge status={t.impl} />
                    </Td>
                </Tr>
            ))}
        </Table>
    );
}

function CredRow({ label, value }: { label: string; value: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div>
            <div
                style={{
                    fontSize: 12,
                    fontWeight: 600,
                    color: 'var(--text-2)',
                    marginBottom: 6,
                }}
            >
                {label}
            </div>
            <div
                className="mono"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '10px 12px',
                    background: 'var(--code-bg)',
                    border: '1px solid var(--border)',
                    borderRadius: 'var(--r-sm)',
                    fontSize: 12.5,
                    color: 'var(--code-text)',
                }}
            >
                <span>{value}</span>
                <button
                    onClick={() => {
                        navigator.clipboard?.writeText(value);
                        setCopied(true);
                        setTimeout(() => setCopied(false), 1200);
                    }}
                    className="maac-link"
                    style={{
                        border: 'none',
                        background: 'none',
                        cursor: 'pointer',
                        color: copied ? 'var(--teal-600)' : 'var(--text-3)',
                        display: 'flex',
                    }}
                >
                    <Icon name={copied ? 'check' : 'copy'} size={14} />
                </button>
            </div>
        </div>
    );
}

function CredentialCard({
    cred,
    onRotate,
    onRevoke,
}: {
    cred: Credential;
    onRotate: () => void;
    onRevoke: () => void;
}) {
    const active = cred.status === 'Active';

    return (
        <div
            style={{
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-md)',
                padding: 14,
                display: 'flex',
                flexDirection: 'column',
                gap: 12,
                opacity: active ? 1 : 0.72,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 10,
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <EnvBadge env={cred.environment} />
                    {cred.label && (
                        <span style={{ fontSize: 13, fontWeight: 600 }}>
                            {cred.label}
                        </span>
                    )}
                </div>
                <Badge tone={active ? 'teal' : 'red'} dot>
                    {cred.status}
                </Badge>
            </div>
            <CredRow label="Client ID" value={cred.clientId} />
            <div>
                <div
                    style={{
                        fontSize: 12,
                        fontWeight: 600,
                        color: 'var(--text-2)',
                        marginBottom: 6,
                    }}
                >
                    Client Secret
                </div>
                <div
                    className="mono"
                    style={{
                        padding: '10px 12px',
                        background: 'var(--code-bg)',
                        border: '1px solid var(--border)',
                        borderRadius: 'var(--r-sm)',
                        fontSize: 12.5,
                        color: 'var(--text-3)',
                    }}
                >
                    {`maac_secret_••••••••••••${cred.lastFour ?? '????'}`}
                </div>
            </div>
            <KV
                cols={2}
                items={[
                    { k: 'Last used', v: cred.lastUsedAt ?? 'Never' },
                    {
                        k: active ? 'Rotated' : 'Revoked',
                        v: active
                            ? (cred.rotatedAt ?? '—')
                            : (cred.revokedAt ?? '—'),
                    },
                ]}
            />
            {active && (
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        alignItems: 'center',
                        paddingTop: 4,
                    }}
                >
                    <Btn
                        variant="default"
                        size="sm"
                        icon="refresh"
                        onClick={onRotate}
                    >
                        Rotate
                    </Btn>
                    <div style={{ flex: 1 }} />
                    <Btn
                        variant="danger"
                        size="sm"
                        icon="power"
                        onClick={onRevoke}
                    >
                        Revoke
                    </Btn>
                </div>
            )}
        </div>
    );
}

function GenerateCredentialModal({
    app,
    open,
    onClose,
}: {
    app: Application;
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const form = useForm({ environment: toEnumValue(app.env), label: '' });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!team) {
            return;
        }

        form.post(storeCredential([team.slug, app.id]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="key"
            title="Generate credential"
            sub={`A new client ID + secret scoped to ${app.name}.`}
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="key"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Generate
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Environment" required>
                    <Select
                        value={form.data.environment}
                        onChange={(v) => form.setData('environment', v)}
                        options={ENV_OPTIONS}
                    />
                    <FieldError error={form.errors.environment} />
                </Field>
                <Field
                    label="Label"
                    hint="Optional — to identify where this credential is used."
                >
                    <Input
                        value={form.data.label}
                        onChange={(e) => form.setData('label', e.target.value)}
                        placeholder="Production server"
                    />
                    <FieldError error={form.errors.label} />
                </Field>
            </div>
        </Modal>
    );
}

function AppCreds({ app }: { app: Application }) {
    const team = useCurrentTeam();
    const credentials = app.credentials ?? [];
    const [showGenerate, setShowGenerate] = useState(false);
    const activeCred = credentials.find((c) => c.status === 'Active');
    const envBlock = `MAAC_CLIENT_ID=${activeCred?.clientId ?? 'generate-a-credential'}
MAAC_CLIENT_SECRET=••••••••••••  # shown once on generation
MAAC_ENVIRONMENT=${app.env.toLowerCase()}`;

    const rotate = (id: string) => {
        if (team) {
            router.post(
                rotateCredential([team.slug, id]).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    const revoke = (id: string) => {
        if (
            team &&
            window.confirm(
                'Revoke this credential? The application will no longer be able to authenticate with it.',
            )
        ) {
            router.post(
                revokeCredential([team.slug, id]).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 360px',
                gap: 14,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader
                        title="API credentials"
                        sub={`Client-credentials grants for ${app.name}`}
                        icon="key"
                        right={
                            <Btn
                                variant="primary"
                                size="sm"
                                icon="plus"
                                onClick={() => setShowGenerate(true)}
                            >
                                Generate credential
                            </Btn>
                        }
                    />
                    {credentials.length === 0 ? (
                        <EmptyState
                            icon="key"
                            title="No credentials yet"
                            desc="Generate a client ID and secret so this application can authenticate to the MAAC SDK."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={() => setShowGenerate(true)}
                                >
                                    Generate credential
                                </Btn>
                            }
                        />
                    ) : (
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 12,
                            }}
                        >
                            {credentials.map((c) => (
                                <CredentialCard
                                    key={c.id}
                                    cred={c}
                                    onRotate={() => rotate(c.id)}
                                    onRevoke={() => revoke(c.id)}
                                />
                            ))}
                        </div>
                    )}
                </Card>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card pad={false}>
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
                            name="doc"
                            size={15}
                            style={{ color: 'var(--primary)' }}
                        />
                        <span style={{ fontSize: 12.5, fontWeight: 700 }}>
                            .env configuration
                        </span>
                    </div>
                    <CodeBlock
                        code={envBlock}
                        style={{ border: 'none', borderRadius: 0 }}
                        copyable
                    />
                </Card>
                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        padding: '12px 14px',
                        background: 'var(--amber-100)',
                        borderRadius: 'var(--r-md)',
                        border: '1px solid var(--amber-500)',
                    }}
                >
                    <Icon
                        name="shield-alert"
                        size={18}
                        style={{ color: 'var(--amber-500)', flexShrink: 0 }}
                    />
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                        }}
                    >
                        Store the Client Secret in a secrets manager. MAAC never
                        stores application database credentials — client-side
                        tools run inside this application's own boundary.
                    </div>
                </div>
            </div>
            <GenerateCredentialModal
                app={app}
                open={showGenerate}
                onClose={() => setShowGenerate(false)}
            />
        </div>
    );
}

function SetupCheck({ label, done }: { label: string; done: boolean }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                fontSize: 12.5,
            }}
        >
            <span
                style={{
                    width: 20,
                    height: 20,
                    borderRadius: 999,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: done ? 'var(--teal-500)' : 'var(--surface-3)',
                    color: done ? '#fff' : 'var(--text-3)',
                    border: done ? 'none' : '1px solid var(--border-2)',
                    flexShrink: 0,
                }}
            >
                {done ? (
                    <Icon name="check" size={12} strokeWidth={3} />
                ) : (
                    <span
                        style={{
                            width: 6,
                            height: 6,
                            borderRadius: 6,
                            background: 'var(--text-3)',
                        }}
                    />
                )}
            </span>
            <span
                style={{
                    fontWeight: 500,
                    color: done ? 'var(--text)' : 'var(--text-2)',
                }}
            >
                {label}
            </span>
        </div>
    );
}

function AppSDK({ app }: { app: Application }) {
    const install = `# Install the MAAC SDK
npm install @qatar-navigation-milaha/sdk`;
    const init = `import { MaacClient } from "@qatar-navigation-milaha/sdk";

const maac = new MaacClient({
  projectId:     process.env.MAAC_PROJECT_ID,
  clientId:      process.env.MAAC_CLIENT_ID,
  clientSecret:  process.env.MAAC_CLIENT_SECRET,
  environment:   "${app.env.toLowerCase()}",
});

// Discover agents available to this application
const agents = await maac.listAgents();`;
    const handler = `import { ToolHandlerRegistry } from "@qatar-navigation-milaha/sdk";

// Register a local handler, keyed by the tool's contract slug.
// MAAC defines the contract; this app owns the execution.
const registry = new ToolHandlerRegistry();

registry.register("getOperationalRecords", (args, ctx) => {
  // Enforce YOUR app's own authorization, then read your own data.
  const result = yourApp.operations.query({
    from: args.from_date, to: args.to_date, vessel: args.vessel_id,
  });
  return { summary: result.summary, records: result.records };
});`;
    const invoke = `// Invoke an agent — pause/resume is handled automatically
const res = await maac.runAgent("operations-summary", {
  input: "Summarize today's operations and flag delays.",
  context: { userId: currentUser.id, department: currentUser.dept },
});
console.log(res.output);`;
    const steps: {
        n: number;
        title: string;
        desc: string;
        code: string;
        lang: string;
    }[] = [
        {
            n: 1,
            title: 'Install the SDK',
            desc: 'Add the MAAC SDK to your application dependencies.',
            code: install,
            lang: 'bash',
        },
        {
            n: 2,
            title: 'Initialize the client',
            desc: 'Configure with the credentials from the Credentials tab.',
            code: init,
            lang: 'typescript',
        },
        {
            n: 3,
            title: 'Register client-side tool handlers',
            desc: 'Implement each required tool against your own data & permissions.',
            code: handler,
            lang: 'typescript',
        },
        {
            n: 4,
            title: 'Invoke agents',
            desc: 'MAAC pauses for client tools and resumes after your handler responds.',
            code: invoke,
            lang: 'typescript',
        },
    ];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 300px',
                gap: 14,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {steps.map((s) => (
                    <Card key={s.n}>
                        <div
                            style={{
                                display: 'flex',
                                gap: 12,
                                marginBottom: 12,
                            }}
                        >
                            <span
                                style={{
                                    width: 26,
                                    height: 26,
                                    borderRadius: 8,
                                    background: 'var(--primary)',
                                    color: 'var(--primary-contrast)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 13,
                                    fontWeight: 700,
                                    flexShrink: 0,
                                }}
                            >
                                {s.n}
                            </span>
                            <div>
                                <div style={{ fontSize: 14, fontWeight: 700 }}>
                                    {s.title}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: 'var(--text-3)',
                                        marginTop: 1,
                                    }}
                                >
                                    {s.desc}
                                </div>
                            </div>
                        </div>
                        <CodeBlock code={s.code} lang={s.lang} />
                    </Card>
                ))}
            </div>
            <div>
                <Card style={{ position: 'sticky', top: 0 }}>
                    <SectionHeader title="Setup status" icon="check2" />
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 10,
                        }}
                    >
                        <SetupCheck label="SDK installed" done />
                        <SetupCheck label="Client initialized" done />
                        <SetupCheck
                            label={`Tool handlers (${app.toolsImplemented}/${app.toolsRequired})`}
                            done={app.toolsImplemented === app.toolsRequired}
                        />
                        <SetupCheck
                            label="First agent invoked"
                            done={app.status === 'Active'}
                        />
                    </div>
                    <Btn
                        variant="soft"
                        size="sm"
                        full
                        style={{ marginTop: 14 }}
                        icon="book"
                    >
                        Full SDK documentation
                    </Btn>
                </Card>
            </div>
        </div>
    );
}

function EditAppModal({
    app,
    open,
    onClose,
    onArchived,
}: {
    app: Application;
    open: boolean;
    onClose: () => void;
    onArchived: () => void;
}) {
    const team = useCurrentTeam();
    const form = useForm({
        name: app.name,
        code: app.code,
        environment: toEnumValue(app.env),
        status: toEnumValue(app.status),
        department: app.dept,
        owner_name: app.owner,
        owner_email: app.ownerEmail,
        stack: app.stack ?? '',
        region: app.region ?? '',
        description: app.desc ?? '',
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!team) {
            return;
        }

        form.put(updateApplication([team.slug, app.id]).url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const archive = () => {
        if (
            team &&
            window.confirm(
                `Archive ${app.name}? It will be hidden from the console.`,
            )
        ) {
            router.delete(archiveApplication([team.slug, app.id]).url, {
                preserveScroll: true,
                onSuccess: onArchived,
            });
        }
    };

    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="apps"
            title="Edit application"
            sub={app.name}
            width={620}
            footer={
                <>
                    <Btn
                        variant="ghost"
                        icon="archive"
                        style={{ color: 'var(--red-600)' }}
                        onClick={archive}
                    >
                        Archive
                    </Btn>
                    <div style={{ flex: 1 }} />
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Save changes
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Application name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <div style={half}>
                    <Field label="Application code" required>
                        <Input
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.code} />
                    </Field>
                    <Field label="Status" required>
                        <Select
                            value={form.data.status}
                            onChange={(v) => form.setData('status', v)}
                            options={APP_STATUS_OPTIONS}
                        />
                        <FieldError error={form.errors.status} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Environment" required>
                        <Select
                            value={form.data.environment}
                            onChange={(v) => form.setData('environment', v)}
                            options={ENV_OPTIONS}
                        />
                        <FieldError error={form.errors.environment} />
                    </Field>
                    <Field label="Owning department" required>
                        <Input
                            value={form.data.department}
                            onChange={(e) =>
                                form.setData('department', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.department} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Business owner" required>
                        <Input
                            value={form.data.owner_name}
                            onChange={(e) =>
                                form.setData('owner_name', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.owner_name} />
                    </Field>
                    <Field label="Owner email" required>
                        <Input
                            type="email"
                            value={form.data.owner_email}
                            onChange={(e) =>
                                form.setData('owner_email', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.owner_email} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Technology stack">
                        <Input
                            value={form.data.stack}
                            onChange={(e) =>
                                form.setData('stack', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.stack} />
                    </Field>
                    <Field label="Region">
                        <Input
                            value={form.data.region}
                            onChange={(e) =>
                                form.setData('region', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.region} />
                    </Field>
                </div>
                <Field label="Description">
                    <Textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                    <FieldError error={form.errors.description} />
                </Field>
            </div>
        </Modal>
    );
}

/* ---- Page ---- */

export default function Show({ id }: { id: string }) {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const team = useCurrentTeam();
    const app = MAAC.appById(id);
    const [tab, setTab] = useState('overview');
    const [showEdit, setShowEdit] = useState(false);

    const setStatus = (status: string) => {
        if (team && app) {
            router.put(
                updateApplication([team.slug, app.id]).url,
                { status },
                { preserveScroll: true },
            );
        }
    };

    if (!app) {
        return <PlaceholderScreen name="Application" />;
    }

    if (!scope.has.app(id)) {
        return <NoAccess kind="application" />;
    }

    const appAgents = MAAC.agentsByApp(id);
    const appProjects = MAAC.projectsByApp(id);
    const appTools = MAAC.tools.filter((t) => t.appId === id);

    const tabs = [
        { id: 'overview', label: 'Overview', icon: 'dashboard' },
        {
            id: 'projects',
            label: 'Projects',
            icon: 'projects',
            count: appProjects.length,
        },
        {
            id: 'agents',
            label: 'Agents',
            icon: 'agents',
            count: appAgents.length,
        },
        {
            id: 'tools',
            label: 'Required Tools',
            icon: 'tools',
            count: appTools.length,
        },
        { id: 'creds', label: 'Credentials', icon: 'key' },
        { id: 'history', label: 'Run History', icon: 'runs' },
        { id: 'sdk', label: 'SDK Setup', icon: 'sdk' },
    ];

    return (
        <>
            <Head title={app ? app.name : 'Application'} />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        {
                            label: 'Applications',
                            onClick: () => go('applications'),
                        },
                        { label: app.name },
                    ]}
                    title={
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <AppMark code={app.id} size={32} />
                            {app.name}
                        </span>
                    }
                    badge={
                        <>
                            <Badge tone={APP_STATUS[app.status].tone} dot>
                                {app.status}
                            </Badge>
                            <EnvBadge env={app.env} />
                        </>
                    }
                    sub={app.desc}
                    actions={
                        <>
                            <Btn
                                variant="default"
                                icon="edit"
                                onClick={() => setShowEdit(true)}
                            >
                                Edit
                            </Btn>
                            <Btn
                                variant="default"
                                icon="key"
                                onClick={() => setTab('creds')}
                            >
                                Manage Credentials
                            </Btn>
                            {app.status === 'Active' ? (
                                <Btn
                                    variant="danger"
                                    icon="power"
                                    onClick={() => setStatus('suspended')}
                                >
                                    Suspend
                                </Btn>
                            ) : (
                                <Btn
                                    variant="primary"
                                    icon="power"
                                    onClick={() => setStatus('active')}
                                >
                                    Activate
                                </Btn>
                            )}
                        </>
                    }
                    tabs={<Tabs tabs={tabs} active={tab} onChange={setTab} />}
                />

                {tab === 'overview' && (
                    <AppOverview
                        app={app}
                        agents={appAgents}
                        projects={appProjects}
                        tools={appTools}
                        go={go}
                        setTab={setTab}
                    />
                )}
                {tab === 'projects' && (
                    <AppProjects projects={appProjects} go={go} />
                )}
                {tab === 'agents' && <AppAgents agents={appAgents} go={go} />}
                {tab === 'tools' && <AppTools tools={appTools} go={go} />}
                {tab === 'creds' && <AppCreds app={app} />}
                {tab === 'history' && <AppHistory app={app} />}
                {tab === 'sdk' && <AppSDK app={app} />}

                <EditAppModal
                    app={app}
                    open={showEdit}
                    onClose={() => setShowEdit(false)}
                    onArchived={() => go('applications')}
                />
            </div>
        </>
    );
}
