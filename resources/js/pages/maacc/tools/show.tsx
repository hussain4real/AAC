/* ============================================================
   MAACC — Tool Detail
   ============================================================ */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy as destroyTool } from '@/actions/App/Http/Controllers/Maacc/ToolContractController';
import {
    NoAccess,
    PlaceholderScreen,
    Timeline,
    schemaJson,
    sdkStub,
} from '@/components/maacc/common';
import type { TimelineItem } from '@/components/maacc/common';
import { ToolFormModal } from '@/components/maacc/tool-form';
import {
    AgentBadge,
    AppMark,
    Btn,
    Card,
    CodeBlock,
    EmptyState,
    EnvBadge,
    ExecChip,
    ImplBadge,
    KV,
    PageHeader,
    SectionHeader,
    Segmented,
    SensBadge,
    Table,
    Td,
    Tr,
    Badge,
    scopeBadge,
} from '@/components/maacc/ui';
import type { Agent, ImplStatus, Tool } from '@/maacc/data';
import { useCurrentTeam } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';

type HistoryVersion = {
    sequence: number;
    version: string;
    created_at: string | null;
    changed_by: string | null;
};

type HistoryEvent = {
    id: string;
    application: string;
    status: string;
    previous_status: string | null;
    created_at: string | null;
    actor: string | null;
};

type ToolHistory = {
    versions: HistoryVersion[];
    events: HistoryEvent[];
} | null;

function fmtAudit(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function eventLook(event: HistoryEvent): {
    icon: string;
    color: string;
    title: string;
} {
    if (event.status === 'incompatible') {
        return {
            icon: 'alert',
            color: 'var(--red-600)',
            title: `Implementation incompatible — ${event.application}`,
        };
    }

    if (event.status === 'outdated') {
        return {
            icon: 'alert',
            color: 'var(--amber-500)',
            title: `Implementation flagged outdated — ${event.application}`,
        };
    }

    if (
        event.previous_status === 'outdated' ||
        event.previous_status === 'incompatible'
    ) {
        return {
            icon: 'refresh',
            color: 'var(--teal-600)',
            title: `Implementation recovered — ${event.application}`,
        };
    }

    return {
        icon: 'check2',
        color: 'var(--teal-600)',
        title: `Implementation validated — ${event.application}`,
    };
}

/**
 * Build the tool's real audit timeline (newest first) from its contract version
 * snapshots and SDK implementation transitions — replacing the former hardcoded
 * mock entries.
 */
function buildAuditTimeline(history: ToolHistory): TimelineItem[] {
    if (!history) {
        return [];
    }

    const dated = [
        ...history.versions.map((version) => ({
            icon: version.sequence === 1 ? 'plus' : 'edit',
            color:
                version.sequence === 1 ? 'var(--primary)' : 'var(--blue-500)',
            title:
                version.sequence === 1
                    ? `Tool contract created — v${version.version}`
                    : `Contract updated — v${version.version}`,
            by: version.changed_by ?? 'system',
            time: fmtAudit(version.created_at),
            ts: version.created_at ? Date.parse(version.created_at) : 0,
        })),
        ...history.events.map((event) => ({
            ...eventLook(event),
            by: event.actor ?? 'sdk.sync',
            time: fmtAudit(event.created_at),
            ts: event.created_at ? Date.parse(event.created_at) : 0,
        })),
    ];

    return dated
        .sort((a, b) => b.ts - a.ts)
        .map((item) => ({
            icon: item.icon,
            color: item.color,
            title: item.title,
            by: item.by,
            time: item.time,
        }));
}

export default function Show({
    id,
    history,
}: {
    id: string;
    history: ToolHistory;
}) {
    const { go, scope } = useMaaccNav();
    const MAACC = useMaaccData();
    const team = useCurrentTeam();
    const tool = MAACC.toolById(id);
    const [showEdit, setShowEdit] = useState(false);

    if (!tool) {
        return <PlaceholderScreen name="Tool" />;
    }

    if (!scope.has.tool(id)) {
        return <NoAccess kind="tool" />;
    }

    const isClient = tool.execMode === 'client';
    const usedByAgents = tool.usedBy
        .map((a) => MAACC.agentById(a))
        .filter((a): a is Agent => Boolean(a));

    const auditItems = buildAuditTimeline(history);
    const owningApp = MAACC.appById(tool.appId ?? '');

    // Per-application/environment implementation rows. Client tools show their
    // real reported handlers (the same records the SDK Implementation Center
    // reads); a client tool with nothing reported yet shows a single "requires
    // implementation" row; server-side tools are hosted by MAACC.
    const implRows: {
        appId: string | null;
        appName: string;
        env: string;
        status: ImplStatus | null;
        lastValidated: string;
    }[] = isClient
        ? (tool.implementations ?? []).length > 0
            ? (tool.implementations ?? []).map((record) => ({
                  appId: owningApp?.id ?? null,
                  appName: owningApp?.name ?? tool.owner,
                  env: record.env,
                  status: record.status,
                  lastValidated: record.lastValidated ?? 'Never',
              }))
            : [
                  {
                      appId: owningApp?.id ?? null,
                      appName: owningApp?.name ?? tool.owner,
                      env: 'Production',
                      status: 'required' as ImplStatus,
                      lastValidated: 'Never',
                  },
              ]
        : MAACC.apps.slice(0, 2).map((app) => ({
              appId: app.id,
              appName: app.name,
              env: app.env,
              status: null,
              lastValidated: '—',
          }));

    const deprecate = () => {
        if (
            team &&
            window.confirm(
                `Deprecate ${tool.name}? It will be removed from the registry.`,
            )
        ) {
            router.delete(destroyTool([team.slug, tool.id]).url, {
                preserveScroll: true,
                onSuccess: () => go('tools'),
            });
        }
    };

    return (
        <>
            <Head title={tool ? tool.name : 'Tool'} />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        { label: 'Tools', onClick: () => go('tools') },
                        { label: tool.name },
                    ]}
                    title={
                        <span className="mono" style={{ fontSize: 21 }}>
                            {tool.name}
                        </span>
                    }
                    badge={
                        <>
                            {scopeBadge(tool.scope)}
                            <ExecChip mode={tool.execMode} />
                        </>
                    }
                    sub={tool.desc}
                    actions={
                        <Btn
                            variant="default"
                            icon="edit"
                            onClick={() => setShowEdit(true)}
                        >
                            Edit
                        </Btn>
                    }
                />

                {isClient && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            padding: '14px 16px',
                            background:
                                'linear-gradient(100deg, var(--orange-100), transparent)',
                            borderRadius: 'var(--r-lg)',
                            border: '1px solid var(--orange-400)',
                            marginBottom: 16,
                        }}
                    >
                        <span
                            style={{
                                width: 40,
                                height: 40,
                                borderRadius: 10,
                                background: 'var(--orange-600)',
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name="link" size={21} />
                        </span>
                        <div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 700,
                                    color: 'var(--text)',
                                }}
                            >
                                Client-side tool — implemented by the
                                integrating application
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.5,
                                    marginTop: 3,
                                }}
                            >
                                This tool must be implemented inside the
                                integrating application using the MAACC SDK.{' '}
                                <b>
                                    MAACC defines the contract; the application
                                    owns the execution.
                                </b>{' '}
                                MAACC never accesses the application's database
                                directly.
                            </div>
                        </div>
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 320px',
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
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Card pad={false}>
                                <div
                                    style={{
                                        padding: '11px 14px',
                                        borderBottom: '1px solid var(--border)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <Icon
                                        name="download"
                                        size={14}
                                        style={{ color: 'var(--primary)' }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 700,
                                        }}
                                    >
                                        Input schema
                                    </span>
                                </div>
                                <CodeBlock
                                    code={schemaJson(tool.input)}
                                    lang="json"
                                    style={{ border: 'none', borderRadius: 0 }}
                                />
                            </Card>
                            <Card pad={false}>
                                <div
                                    style={{
                                        padding: '11px 14px',
                                        borderBottom: '1px solid var(--border)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <Icon
                                        name="external"
                                        size={14}
                                        style={{ color: 'var(--teal-600)' }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 700,
                                        }}
                                    >
                                        Output schema
                                    </span>
                                </div>
                                <CodeBlock
                                    code={schemaJson(tool.output)}
                                    lang="json"
                                    style={{ border: 'none', borderRadius: 0 }}
                                />
                            </Card>
                        </div>

                        {isClient && <SDKStubs tool={tool} />}

                        <Card pad={false}>
                            <div style={{ padding: '14px 16px 12px' }}>
                                <SectionHeader
                                    title="Implementation status by application"
                                    icon="apps"
                                    style={{ marginBottom: 0 }}
                                />
                            </div>
                            <Table
                                columns={[
                                    { label: 'Application' },
                                    { label: 'Environment' },
                                    { label: 'Status' },
                                    { label: 'Last validated', align: 'right' },
                                ]}
                            >
                                {implRows.map((row, i) => (
                                    <Tr
                                        key={`${row.appId ?? 'app'}-${row.env}-${i}`}
                                        onClick={
                                            row.appId
                                                ? () =>
                                                      go('application', {
                                                          id: row.appId!,
                                                      })
                                                : undefined
                                        }
                                    >
                                        <Td strong>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 9,
                                                }}
                                            >
                                                <AppMark
                                                    code={
                                                        row.appId ?? tool.owner
                                                    }
                                                    size={26}
                                                />
                                                {row.appName}
                                            </div>
                                        </Td>
                                        <Td>
                                            <EnvBadge env={row.env} />
                                        </Td>
                                        <Td>
                                            {row.status ? (
                                                <ImplBadge
                                                    status={row.status}
                                                />
                                            ) : (
                                                <Badge tone="teal" dot>
                                                    Hosted by MAACC
                                                </Badge>
                                            )}
                                        </Td>
                                        <Td
                                            align="right"
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            {row.lastValidated}
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                        </Card>

                        <Card>
                            <SectionHeader
                                title="Audit history"
                                sub="Contract version snapshots and SDK implementation transitions, newest first."
                                icon="runs"
                            />
                            {auditItems.length === 0 ? (
                                <EmptyState
                                    icon="runs"
                                    title="No recorded history yet"
                                    desc="Version changes and SDK implementation reports for this tool will appear here as they happen."
                                />
                            ) : (
                                <Timeline items={auditItems} />
                            )}
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
                            <SectionHeader title="Contract" icon="info" />
                            <KV
                                cols={1}
                                items={[
                                    {
                                        k: 'Execution mode',
                                        v: <ExecChip mode={tool.execMode} />,
                                    },
                                    { k: 'Scope', v: scopeBadge(tool.scope) },
                                    {
                                        k: 'Data sensitivity',
                                        v: (
                                            <SensBadge
                                                level={tool.sensitivity}
                                            />
                                        ),
                                    },
                                    {
                                        k: 'Requires approval',
                                        v: tool.approval ? 'Yes' : 'No',
                                    },
                                    {
                                        k: 'Timeout',
                                        v: tool.timeout,
                                        mono: true,
                                    },
                                    {
                                        k: 'Max payload',
                                        v: tool.maxPayload,
                                        mono: true,
                                    },
                                    {
                                        k: 'Owner',
                                        v:
                                            tool.owner === 'Platform'
                                                ? 'Platform team'
                                                : (MAACC.appById(tool.owner)
                                                      ?.name ?? tool.owner),
                                    },
                                ]}
                            />
                        </Card>
                        <Card>
                            <SectionHeader
                                title={`Used by ${usedByAgents.length} agents`}
                                icon="agents"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 7,
                                }}
                            >
                                {usedByAgents.map((a) => (
                                    <div
                                        key={a.id}
                                        className="maacc-row"
                                        onClick={() =>
                                            go('agent', { id: a.id })
                                        }
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 9,
                                            padding: '7px 9px',
                                            borderRadius: 7,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 26,
                                                height: 26,
                                                borderRadius: 7,
                                                background:
                                                    'var(--primary-soft)',
                                                color: 'var(--primary)',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                flexShrink: 0,
                                            }}
                                        >
                                            <Icon name="agents" size={14} />
                                        </span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    fontWeight: 600,
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                }}
                                            >
                                                {a.name}
                                            </div>
                                        </div>
                                        <AgentBadge status={a.status} />
                                    </div>
                                ))}
                            </div>
                        </Card>
                        <Btn
                            variant="danger"
                            icon="archive"
                            full
                            onClick={deprecate}
                        >
                            Deprecate Tool
                        </Btn>
                    </div>
                </div>

                <ToolFormModal
                    tool={tool}
                    open={showEdit}
                    onClose={() => setShowEdit(false)}
                />
            </div>
        </>
    );
}

/* ---- local sub-component (ported verbatim from prototype) ---- */

function SDKStubs({ tool }: { tool: Tool }) {
    const [lang, setLang] = useState<'ts' | 'php' | 'py'>('ts');
    const argList = Object.keys(tool.input);
    const outList = Object.keys(tool.output);
    const stubs: Record<'ts' | 'php' | 'py', string> = {
        ts: sdkStub(tool, 'ts', argList, outList),
        php: sdkStub(tool, 'php', argList, outList),
        py: sdkStub(tool, 'py', argList, outList),
    };

    return (
        <Card pad={false}>
            <div
                style={{
                    padding: '13px 16px 12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <SectionHeader
                    title="Generated SDK stub"
                    sub="Copy into the owning application to implement this tool"
                    icon="code"
                    style={{ marginBottom: 0 }}
                />
                <Segmented
                    options={[
                        { value: 'ts', label: 'TypeScript' },
                        { value: 'php', label: 'PHP / Laravel' },
                        { value: 'py', label: 'Python' },
                    ]}
                    value={lang}
                    onChange={(v) => setLang(v as 'ts' | 'php' | 'py')}
                    size="sm"
                />
            </div>
            <CodeBlock
                code={stubs[lang]}
                lang={
                    lang === 'ts'
                        ? 'typescript'
                        : lang === 'php'
                          ? 'php'
                          : 'python'
                }
                style={{ border: 'none', borderRadius: 0 }}
                maxHeight={340}
            />
        </Card>
    );
}
