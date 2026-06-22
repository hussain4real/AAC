/* ============================================================
   MAAC — SDK Implementation Center
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Progress } from '@/components/maac/charts';
import { Lbl, schemaJson, sdkStub } from '@/components/maac/common';
import {
    AppMark,
    Badge,
    Btn,
    Card,
    CodeBlock,
    EnvBadge,
    ImplBadge,
    PageHeader,
    SectionHeader,
    SensBadge,
    Segmented,
    Select,
    Table,
    Td,
    Tr,
} from '@/components/maac/ui';
import type { Tone } from '@/components/maac/ui';
import type {
    Application,
    Environment,
    ImplStatus,
    Tool,
    ToolImplementationRecord,
} from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { MaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ── helpers ── */

/** Resolve a client tool's reported implementation for an environment. */
function implFor(
    tool: Tool,
    env: Environment,
): ToolImplementationRecord | undefined {
    return tool.implementations?.find((record) => record.env === env);
}

/** The effective implementation status for a tool in an environment. */
function implStatusFor(tool: Tool, env: Environment): ImplStatus {
    return implFor(tool, env)?.status ?? tool.impl;
}

/* ── local sub-components ── */

interface SDKStatProps {
    icon: string;
    label: string;
    value: string | number;
    ok: boolean;
    border?: boolean;
}

function SDKStat({ icon, label, value, ok, border }: SDKStatProps) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 11,
                padding: '13px 18px',
                borderLeft: border ? '1px solid var(--border)' : 'none',
            }}
        >
            <span
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: 8,
                    background: ok ? 'var(--teal-100)' : 'var(--red-100)',
                    color: ok ? 'var(--teal-600)' : 'var(--red-600)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                <Icon name={icon} size={16} />
            </span>
            <div>
                <div
                    style={{
                        fontSize: 11,
                        color: 'var(--text-3)',
                        fontWeight: 600,
                    }}
                >
                    {label}
                </div>
                <div
                    className="tnum"
                    style={{ fontSize: 14.5, fontWeight: 700, marginTop: 1 }}
                >
                    {value}
                </div>
            </div>
        </div>
    );
}

interface ToolImplPanelProps {
    tool: Tool;
    app: Application;
    onClose: () => void;
    go: MaacNav['go'];
}

function ToolImplPanel({ tool, app, onClose }: ToolImplPanelProps) {
    const MAAC = useMaacData();
    const [lang, setLang] = useState<'ts' | 'php' | 'py'>('ts');
    const [copied, setCopied] = useState(false);
    const ag = MAAC.agentById(tool.usedBy[0]);
    const argList = Object.keys(tool.input);
    const stub = sdkStub(tool, lang, argList);
    const status = implStatusFor(tool, app.env);
    const isImplemented = status === 'implemented';
    const checklist = [
        {
            label: 'Install MAAC SDK in the application',
            done: !!app.lastSyncedAt,
        },
        {
            label: `Register a handler for ${tool.name}`,
            done: isImplemented,
        },
        {
            label: 'Enforce caller permissions before returning data',
            done: isImplemented,
        },
        {
            label: 'Validate the result against the output schema with the SDK ToolTester before reporting',
            done: isImplemented,
        },
        {
            label: 'MAAC re-validates every result against the output schema and rejects a mismatch (invalid_tool_result)',
            done: isImplemented,
        },
        {
            label: 'Report the implementation & sync status to MAAC',
            done: isImplemented,
        },
    ];

    return (
        <div style={{ animation: 'fadeUp .25s ease both' }}>
            <Card
                pad={false}
                style={{ position: 'sticky', top: 0, overflow: 'hidden' }}
            >
                <div
                    style={{
                        padding: '14px 16px',
                        borderBottom: '1px solid var(--border)',
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 11,
                        background:
                            'linear-gradient(100deg, var(--orange-100), transparent)',
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
                        }}
                    >
                        <Icon name="link" size={19} />
                    </span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div
                            className="mono"
                            style={{ fontSize: 14, fontWeight: 700 }}
                        >
                            {tool.name}
                        </div>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--text-2)',
                                marginTop: 2,
                            }}
                        >
                            Used by {ag?.name}
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="maac-iconbtn"
                        style={{
                            border: 'none',
                            background: 'none',
                            cursor: 'pointer',
                            color: 'var(--text-3)',
                            padding: 4,
                            borderRadius: 6,
                            display: 'flex',
                        }}
                    >
                        <Icon name="x" size={17} />
                    </button>
                </div>

                <div
                    className="maac-scroll"
                    style={{
                        maxHeight: 'calc(100vh - 220px)',
                        overflowY: 'auto',
                        padding: '15px 16px',
                    }}
                >
                    <div style={{ marginBottom: 14 }}>
                        <Lbl>Description</Lbl>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: 'var(--text-2)',
                                lineHeight: 1.55,
                            }}
                        >
                            {tool.desc}
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 6,
                                marginTop: 9,
                                flexWrap: 'wrap',
                            }}
                        >
                            <ImplBadge status={status} />
                            <SensBadge level={tool.sensitivity} />
                            <Badge tone="neutral">timeout {tool.timeout}</Badge>
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 10,
                            marginBottom: 15,
                        }}
                    >
                        <div>
                            <Lbl>Input schema</Lbl>
                            <CodeBlock
                                code={schemaJson(tool.input)}
                                style={{ fontSize: 11 }}
                                copyable={false}
                            />
                        </div>
                        <div>
                            <Lbl>Output schema</Lbl>
                            <CodeBlock
                                code={schemaJson(tool.output)}
                                style={{ fontSize: 11 }}
                                copyable={false}
                            />
                        </div>
                    </div>

                    <div style={{ marginBottom: 15 }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                marginBottom: 8,
                            }}
                        >
                            <Lbl style={{ margin: 0 }}>Generated SDK stub</Lbl>
                            <Segmented
                                options={[
                                    { value: 'ts', label: 'TS' },
                                    { value: 'php', label: 'PHP' },
                                    { value: 'py', label: 'Py' },
                                ]}
                                value={lang}
                                onChange={(v) =>
                                    setLang(v as 'ts' | 'php' | 'py')
                                }
                                size="sm"
                            />
                        </div>
                        <CodeBlock
                            code={stub}
                            lang={
                                lang === 'ts'
                                    ? 'typescript'
                                    : lang === 'php'
                                      ? 'php'
                                      : 'python'
                            }
                            maxHeight={260}
                            copyable={false}
                        />
                        <Btn
                            variant="primary"
                            full
                            icon={copied ? 'check' : 'copy'}
                            style={{ marginTop: 10 }}
                            onClick={() => {
                                navigator.clipboard?.writeText(stub);
                                setCopied(true);
                                setTimeout(() => setCopied(false), 1500);
                            }}
                        >
                            {copied ? 'Copied to clipboard' : 'Copy SDK Stub'}
                        </Btn>
                    </div>

                    <div>
                        <Lbl>Implementation checklist</Lbl>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 9,
                            }}
                        >
                            {checklist.map((c, i) => (
                                <div
                                    key={i}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        fontSize: 12.5,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 19,
                                            height: 19,
                                            borderRadius: 999,
                                            flexShrink: 0,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            background: c.done
                                                ? 'var(--teal-500)'
                                                : 'var(--surface-3)',
                                            color: c.done
                                                ? '#fff'
                                                : 'var(--text-3)',
                                            border: c.done
                                                ? 'none'
                                                : '1px solid var(--border-2)',
                                        }}
                                    >
                                        {c.done ? (
                                            <Icon
                                                name="check"
                                                size={11}
                                                strokeWidth={3}
                                            />
                                        ) : (
                                            i + 1
                                        )}
                                    </span>
                                    <span
                                        style={{
                                            color: c.done
                                                ? 'var(--text-3)'
                                                : 'var(--text)',
                                            textDecoration: c.done
                                                ? 'line-through'
                                                : 'none',
                                        }}
                                    >
                                        {c.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    );
}

/* ── SDK versioning & compatibility (Phase 6C) ── */

/** Map a compatibility/implementation status to a badge tone. */
function compatTone(status: string): Tone {
    switch (status) {
        case 'compatible':
        case 'ahead':
        case 'implemented':
            return 'teal';
        case 'outdated':
            return 'amber';
        case 'upgrade_required':
        case 'incompatible':
            return 'red';
        default:
            return 'neutral';
    }
}

/**
 * The versioned SDK contract surface: MAAC's API contract version, the supported
 * client-package window, published packages, active deprecations, each
 * application's reported SDK client compatibility, and the contract drift feed
 * (client-side tools whose implementation has fallen behind their contract).
 */
function SdkVersioningPanel() {
    const MAAC = useMaacData();
    const { platform, applications, drift } = MAAC.sdkCompatibility;

    return (
        <div style={{ marginBottom: 16 }}>
            <Card pad={false} style={{ overflow: 'hidden', marginBottom: 14 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        padding: '15px 18px',
                        background:
                            'linear-gradient(100deg, var(--primary-soft), transparent)',
                    }}
                >
                    <span
                        style={{
                            width: 38,
                            height: 38,
                            borderRadius: 9,
                            background: 'var(--primary)',
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            flexShrink: 0,
                        }}
                    >
                        <Icon name="layers" size={20} />
                    </span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 15, fontWeight: 700 }}>
                            SDK Versioning &amp; Compatibility
                        </div>
                        <div style={{ fontSize: 12.5, color: 'var(--text-2)' }}>
                            The versioned integration contract — detect SDK and
                            tool-implementation compatibility before deployment.
                        </div>
                    </div>
                    <Badge tone="purple" icon="link">
                        API v{platform.api_version}
                    </Badge>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        borderTop: '1px solid var(--border)',
                    }}
                >
                    <SDKStat
                        icon="link"
                        label="API contract version"
                        value={`v${platform.api_version}`}
                        ok
                    />
                    <SDKStat
                        icon="check2"
                        label="Supported client window"
                        value={`${platform.minimum_client_version} – ${platform.current_client_version}`}
                        border
                        ok
                    />
                    <SDKStat
                        icon="book"
                        label="SDK packages"
                        value={platform.packages.length}
                        border
                        ok
                    />
                    <SDKStat
                        icon={platform.deprecations.length ? 'alert' : 'check2'}
                        label="Active deprecations"
                        value={platform.deprecations.length}
                        border
                        ok={platform.deprecations.length === 0}
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 8,
                        padding: '12px 18px',
                        borderTop: '1px solid var(--border)',
                    }}
                >
                    {platform.packages.map((pkg) => (
                        <Badge
                            key={pkg.language}
                            tone={
                                pkg.status === 'supported' ? 'teal' : 'neutral'
                            }
                        >
                            {pkg.name}
                            {pkg.version ? `@${pkg.version}` : ' (planned)'}
                        </Badge>
                    ))}
                </div>

                {platform.deprecations.length > 0 && (
                    <div
                        style={{
                            padding: '12px 18px',
                            borderTop: '1px solid var(--border)',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        {platform.deprecations.map((dep, i) => (
                            <div
                                key={dep.id ?? i}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    fontSize: 12.5,
                                }}
                            >
                                <Badge tone="amber" icon="alert">
                                    removed in {dep.removed_in ?? '—'}
                                </Badge>
                                <span style={{ color: 'var(--text-2)' }}>
                                    {dep.summary ?? dep.id}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            <Card pad={false} style={{ marginBottom: 14 }}>
                <div style={{ padding: '14px 16px 4px' }}>
                    <SectionHeader
                        title="Application SDK compatibility"
                        sub="Reported SDK client version and tool-implementation health per application"
                        icon="apps"
                        style={{ marginBottom: 0 }}
                    />
                </div>
                <Table
                    columns={[
                        { label: 'Application' },
                        { label: 'Environment' },
                        { label: 'Reported SDK client' },
                        { label: 'Compatibility' },
                        { label: 'Tools implemented', align: 'right' },
                        { label: 'Drifted', align: 'right' },
                    ]}
                >
                    {applications.map((app) => {
                        const drifted =
                            app.tools.outdated + app.tools.incompatible;

                        return (
                            <Tr key={app.id}>
                                <Td strong>{app.name}</Td>
                                <Td>
                                    <Badge tone="neutral">
                                        {app.environment}
                                    </Badge>
                                </Td>
                                <Td>
                                    {app.clients.length ? (
                                        <span
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                flexWrap: 'wrap',
                                            }}
                                        >
                                            {app.clients.map((c, i) => (
                                                <span
                                                    key={i}
                                                    className="mono"
                                                    style={{ fontSize: 12 }}
                                                >
                                                    {c.language ?? 'SDK'} v
                                                    {c.version}
                                                </span>
                                            ))}
                                        </span>
                                    ) : (
                                        <span
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            Not reported
                                        </span>
                                    )}
                                </Td>
                                <Td>
                                    {app.clients.length ? (
                                        <Badge
                                            tone={
                                                app.compatible ? 'teal' : 'red'
                                            }
                                            icon={
                                                app.compatible
                                                    ? 'check2'
                                                    : 'alert'
                                            }
                                        >
                                            {app.compatible
                                                ? 'Compatible'
                                                : 'Upgrade required'}
                                        </Badge>
                                    ) : (
                                        <Badge tone="neutral">No client</Badge>
                                    )}
                                </Td>
                                <Td
                                    align="right"
                                    style={{ color: 'var(--text-2)' }}
                                >
                                    <span className="tnum">
                                        {app.tools.implemented}/
                                        {app.tools.total}
                                    </span>
                                </Td>
                                <Td align="right">
                                    {drifted > 0 ? (
                                        <Badge tone="amber">{drifted}</Badge>
                                    ) : (
                                        <span
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            —
                                        </span>
                                    )}
                                </Td>
                            </Tr>
                        );
                    })}
                </Table>
            </Card>

            <Card pad={false}>
                <div style={{ padding: '14px 16px 4px' }}>
                    <SectionHeader
                        title="Contract changes requiring migration"
                        sub="Client-side tools whose reported implementation has drifted from the current contract version"
                        icon="alert"
                        style={{ marginBottom: 0 }}
                    />
                </div>
                {drift.length === 0 ? (
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            alignItems: 'center',
                            padding: '14px 18px',
                        }}
                    >
                        <span
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 9,
                                background: 'var(--teal-100)',
                                color: 'var(--teal-600)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name="check2" size={18} />
                        </span>
                        <div style={{ fontSize: 12.5, color: 'var(--text-2)' }}>
                            All reported tool implementations are current with
                            their contracts. No migrations are pending.
                        </div>
                    </div>
                ) : (
                    <Table
                        columns={[
                            { label: 'Application' },
                            { label: 'Tool' },
                            { label: 'Status' },
                            { label: 'Contract' },
                            { label: 'Implemented', align: 'right' },
                            { label: 'Environment', align: 'right' },
                        ]}
                    >
                        {drift.map((row, i) => (
                            <Tr key={i}>
                                <Td strong>{row.application}</Td>
                                <Td mono>{row.tool}</Td>
                                <Td>
                                    <Badge tone={compatTone(row.status)}>
                                        {row.status}
                                    </Badge>
                                </Td>
                                <Td mono>v{row.contractVersion}</Td>
                                <Td align="right" mono>
                                    {row.implementedVersion
                                        ? `v${row.implementedVersion}`
                                        : '—'}
                                </Td>
                                <Td
                                    align="right"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    {row.environment}
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}
            </Card>
        </div>
    );
}

/* ── page ── */

export default function SDKCenter() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const appsInScope = scope.apps.length ? scope.apps : MAAC.apps;
    const [appId, setAppId] = useState(() => {
        const withMissing = appsInScope.find((a) =>
            MAAC.tools.some(
                (t) =>
                    t.appId === a.id &&
                    t.execMode === 'client' &&
                    implStatusFor(t, a.env) !== 'implemented',
            ),
        );

        return (withMissing || appsInScope[0]).id;
    });
    const [selTool, setSelTool] = useState<string | null>(null);
    const app = MAAC.appById(appId)!;
    const appTools = MAAC.tools.filter(
        (t) => t.appId === appId && t.execMode === 'client',
    );
    const appAgents = MAAC.agentsByApp(appId);

    const done = appTools.filter(
        (t) => implStatusFor(t, app.env) === 'implemented',
    ).length;
    const pct = appTools.length
        ? Math.round((done / appTools.length) * 100)
        : 100;

    return (
        <>
            <Head title="SDK Implementation Center" />
            <div className="route-anim">
                <PageHeader
                    title="SDK Implementation Center"
                    sub="The integration checklist for application developers — exactly which client-side tools to implement, their schemas, and copy-paste SDK stubs."
                    actions={
                        <Btn variant="default" icon="book">
                            SDK Docs
                        </Btn>
                    }
                />

                <SdkVersioningPanel />

                {/* app selector */}
                <Card
                    style={{ padding: 0, marginBottom: 16, overflow: 'hidden' }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 16,
                            padding: '15px 18px',
                            background:
                                'linear-gradient(100deg, var(--primary-soft), transparent)',
                        }}
                    >
                        <div style={{ minWidth: 230 }}>
                            <div
                                style={{
                                    fontSize: 11,
                                    fontWeight: 700,
                                    color: 'var(--text-3)',
                                    textTransform: 'uppercase',
                                    letterSpacing: 0.4,
                                    marginBottom: 6,
                                }}
                            >
                                Application
                            </div>
                            <Select
                                value={appId}
                                onChange={(v) => {
                                    setAppId(v);
                                    setSelTool(null);
                                }}
                                options={appsInScope.map((a) => ({
                                    value: a.id,
                                    label: a.name,
                                }))}
                            />
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                            }}
                        >
                            <AppMark code={app.id} size={42} />
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>
                                    {app.name}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    {app.stack} ·{' '}
                                    <span className="mono">{app.code}</span>
                                </div>
                            </div>
                        </div>
                        <div style={{ flex: 1 }} />
                        <div style={{ textAlign: 'right' }}>
                            <div
                                style={{
                                    fontSize: 11,
                                    color: 'var(--text-3)',
                                    marginBottom: 4,
                                }}
                            >
                                Implementation progress
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <div style={{ width: 160 }}>
                                    <Progress
                                        value={done}
                                        max={appTools.length || 1}
                                        color={
                                            pct === 100
                                                ? 'var(--teal-500)'
                                                : 'var(--orange-600)'
                                        }
                                    />
                                </div>
                                <span
                                    className="tnum"
                                    style={{ fontSize: 15, fontWeight: 700 }}
                                >
                                    {done}/{appTools.length}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(4,1fr)',
                            borderTop: '1px solid var(--border)',
                        }}
                    >
                        <SDKStat
                            icon="check2"
                            label="SDK connection"
                            value={
                                app.credStatus === 'Active'
                                    ? 'Connected'
                                    : 'No active credential'
                            }
                            ok={app.credStatus === 'Active'}
                        />
                        <SDKStat
                            icon="agents"
                            label="Agents available"
                            value={appAgents.length}
                            border
                            ok
                        />
                        <SDKStat
                            icon="link"
                            label="Client tools"
                            value={appTools.length}
                            border
                            ok
                        />
                        <SDKStat
                            icon="refresh"
                            label="Last sync"
                            value={app.lastSyncedAt ?? 'Never'}
                            border
                            ok={!!app.lastSyncedAt}
                        />
                    </div>
                </Card>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: selTool ? '1fr 420px' : '1fr',
                        gap: 14,
                        transition: 'all .2s',
                    }}
                >
                    <div>
                        <Card pad={false}>
                            <div
                                style={{
                                    padding: '14px 16px 12px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <SectionHeader
                                    title="Required client-side tools"
                                    sub="Tools this application must implement via the SDK"
                                    icon="link"
                                    style={{ marginBottom: 0 }}
                                />
                                <Btn variant="default" size="sm" icon="refresh">
                                    Re-validate
                                </Btn>
                            </div>
                            <Table
                                columns={[
                                    { label: 'Tool Name' },
                                    { label: 'Used By Agent' },
                                    { label: 'Environment' },
                                    { label: 'Status' },
                                    { label: 'Last Validated', align: 'right' },
                                    { label: 'Action', align: 'right' },
                                ]}
                            >
                                {appTools.map((t) => {
                                    const ag = MAAC.agentById(t.usedBy[0]);
                                    const isSel = selTool === t.id;
                                    const impl = implFor(t, app.env);
                                    const status = impl?.status ?? t.impl;

                                    return (
                                        <Tr
                                            key={t.id}
                                            onClick={() => setSelTool(t.id)}
                                        >
                                            <Td strong mono>
                                                <span
                                                    style={{
                                                        color: isSel
                                                            ? 'var(--primary)'
                                                            : 'var(--text)',
                                                    }}
                                                >
                                                    {t.name}
                                                </span>
                                            </Td>
                                            <Td>
                                                {ag?.name.replace(' Agent', '')}
                                            </Td>
                                            <Td>
                                                <EnvBadge env={app.env} />
                                            </Td>
                                            <Td>
                                                <ImplBadge status={status} />
                                            </Td>
                                            <Td
                                                align="right"
                                                style={{
                                                    color: 'var(--text-3)',
                                                }}
                                            >
                                                {impl?.lastValidated ?? 'Never'}
                                            </Td>
                                            <Td align="right">
                                                {status === 'implemented' ? (
                                                    <Badge
                                                        tone="teal"
                                                        icon="check2"
                                                    >
                                                        Done
                                                    </Badge>
                                                ) : (
                                                    <span
                                                        className="maac-link"
                                                        style={{
                                                            color: 'var(--primary)',
                                                            fontWeight: 600,
                                                            fontSize: 12,
                                                            display:
                                                                'inline-flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 4,
                                                        }}
                                                    >
                                                        Implement{' '}
                                                        <Icon
                                                            name="chevright"
                                                            size={13}
                                                        />
                                                    </span>
                                                )}
                                            </Td>
                                        </Tr>
                                    );
                                })}
                            </Table>
                        </Card>

                        {!selTool && (
                            <Card style={{ marginTop: 14 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 14,
                                        alignItems: 'center',
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 44,
                                            height: 44,
                                            borderRadius: 11,
                                            background: 'var(--primary-soft)',
                                            color: 'var(--primary)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            flexShrink: 0,
                                        }}
                                    >
                                        <Icon name="info" size={22} />
                                    </span>
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 700,
                                            }}
                                        >
                                            How client-side execution works
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: 'var(--text-2)',
                                                lineHeight: 1.55,
                                                marginTop: 3,
                                            }}
                                        >
                                            Select a tool above to see its
                                            contract, schemas, a generated SDK
                                            stub, and an implementation
                                            checklist. Your application runs the
                                            tool against its{' '}
                                            <b>own database and permissions</b>{' '}
                                            — MAAC only orchestrates.
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>

                    {selTool && (
                        <ToolImplPanel
                            tool={MAAC.toolById(selTool)!}
                            app={app}
                            onClose={() => setSelTool(null)}
                            go={go}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
