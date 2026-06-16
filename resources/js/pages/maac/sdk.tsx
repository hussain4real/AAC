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
import type { Application, Tool } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { MaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

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
    const checklist = [
        {
            label: 'Install MAAC SDK in the application',
            done: app.status !== 'Suspended',
        },
        {
            label: `Register handler for ${tool.name}`,
            done: tool.impl === 'implemented',
        },
        {
            label: 'Enforce caller permissions before returning data',
            done: tool.impl === 'implemented',
        },
        {
            label: 'Return a result matching the output schema',
            done: tool.impl === 'implemented',
        },
        {
            label: 'Run SDK validation & sync status to MAAC',
            done: tool.impl === 'implemented',
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
                            <ImplBadge status={tool.impl} />
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
                    t.impl !== 'implemented',
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

    const done = appTools.filter((t) => t.impl === 'implemented').length;
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
                            label="SDK installed"
                            value={
                                app.status !== 'Suspended'
                                    ? 'v2.4.1'
                                    : 'Not installed'
                            }
                            ok={app.status !== 'Suspended'}
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
                            value={
                                app.status === 'Suspended'
                                    ? '3 days ago'
                                    : '2 min ago'
                            }
                            border
                            ok={app.status !== 'Suspended'}
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
                                                <ImplBadge status={t.impl} />
                                            </Td>
                                            <Td
                                                align="right"
                                                style={{
                                                    color: 'var(--text-3)',
                                                }}
                                            >
                                                {t.impl === 'implemented'
                                                    ? '2h ago'
                                                    : t.impl === 'outdated'
                                                      ? '8d ago'
                                                      : 'Never'}
                                            </Td>
                                            <Td align="right">
                                                {t.impl === 'implemented' ? (
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
