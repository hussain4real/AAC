/* ============================================================
   MAAC — Dashboard (role/scope aware operations overview)
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    AreaSpark,
    Donut,
    DonutLegend,
    HBars,
    Progress,
    StatCard,
} from '@/components/maac/charts';
import { ScopeBanner } from '@/components/maac/common';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    ImplBadge,
    PageHeader,
    RunBadge,
    SectionHeader,
    Table,
    Td,
    Tr,
} from '@/components/maac/ui';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { RouteName } from '@/maac/nav';
import { navAllowed } from '@/maac/personas';
import { useMaacData } from '@/maac/use-data';
import type { DashboardInvitation } from '@/types';

type Props = { pendingInvitations?: DashboardInvitation[] };

export default function Dashboard({ pendingInvitations = [] }: Props) {
    const MAAC = useMaacData();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const { go, scope } = useMaacNav();
    const isAll = scope.isAll;
    const D = MAAC.dashboard;
    const gstat = D.stats;

    const runs7d = scope.agents.reduce((s, a) => s + a.runs7d, 0);
    const runsToday = isAll
        ? gstat.runsToday
        : Math.max(1, Math.round(runs7d / 7));
    const scaleVsAll = runsToday / gstat.runsToday;
    const waitingScoped = scope.runs.filter(
        (r) => r.status === 'waiting_for_client',
    ).length;
    const activeApps = scope.apps.filter((a) => a.status === 'Active').length;
    const suspApps = scope.apps.filter((a) => a.status !== 'Active').length;
    const pubAgents = scope.agents.filter(
        (a) => a.status === 'Published',
    ).length;
    const clientTools = scope.tools.filter(
        (t) => t.execMode === 'client',
    ).length;

    const stat = isAll
        ? gstat
        : {
              apps: scope.apps.length,
              projects: scope.projects.length,
              agents: scope.agents.length,
              tools: scope.tools.length,
              runsToday,
              waitingClient: Math.max(
                  waitingScoped,
                  scope.tools.some((t) =>
                      ['required', 'outdated', 'incompatible'].includes(t.impl),
                  )
                      ? 1
                      : 0,
              ),
              success: Math.round(runsToday * 0.95),
              failed: Math.max(0, Math.round(runsToday * 0.025)),
              tokens: ((runsToday * 3760) / 1e6).toFixed(2) + 'M',
              cost: 'QAR ' + Math.round(runsToday * 1.5).toLocaleString(),
          };

    const runStatus = isAll
        ? D.runStatus
        : [
              {
                  label: 'Completed',
                  value: stat.success,
                  color: 'var(--teal-500)',
              },
              {
                  label: 'Waiting for client',
                  value: Math.max(1, stat.waitingClient),
                  color: 'var(--orange-600)',
              },
              {
                  label: 'Running',
                  value: Math.max(1, Math.round(runsToday * 0.012)),
                  color: 'var(--blue-500)',
              },
              {
                  label: 'Failed',
                  value: Math.max(1, stat.failed),
                  color: 'var(--red-500)',
              },
          ];
    const runsOverTime = isAll
        ? D.runsOverTime
        : D.runsOverTime.map((v) => Math.max(1, Math.round(v * scaleVsAll)));

    const recentRuns = (isAll ? MAAC.runs : scope.runs).slice(0, 6);
    const topAgents = [...scope.agents]
        .sort((a, b) => b.runs7d - a.runs7d)
        .slice(0, 5)
        .map((a) => ({ id: a.id, name: a.name, runs: a.runs7d, app: a.appId }));
    const toolsNeedImpl = scope.tools.filter((t) =>
        ['required', 'outdated', 'incompatible'].includes(t.impl),
    );

    const llmData = (() => {
        if (isAll) {
            return MAAC.llms
                .filter((l) => l.usagePct > 0)
                .slice(0, 5)
                .map((l) => ({ label: l.name, value: l.usagePct }));
        }

        const c: Record<string, number> = {};
        scope.agents.forEach((a) => {
            c[a.llm] = (c[a.llm] || 0) + a.runs7d;
        });

        return Object.entries(c)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5)
            .map(([id, v]) => ({
                label: MAAC.llmById(id)?.name ?? id,
                value: v,
            }));
    })();
    const llmMax = isAll ? 40 : Math.max(...llmData.map((d) => d.value), 1);

    const canGov = navAllowed(scope.role.id, 'governance');

    const statCards: {
        label: string;
        value: number;
        icon: string;
        tone: 'purple' | 'blue' | 'teal' | 'amber';
        sub: string;
        screen: RouteName | null;
    }[] = [
        {
            label: 'Applications',
            value: stat.apps,
            icon: 'apps',
            tone: 'purple',
            sub: `${activeApps} active · ${suspApps} ${suspApps === 1 ? 'other' : 'others'}`,
            screen: scope.role.id === 'dev' ? null : 'applications',
        },
        {
            label: 'Active Projects',
            value: stat.projects,
            icon: 'projects',
            tone: 'blue',
            sub: isAll
                ? 'Across 5 applications'
                : `Across ${scope.apps.length} application${scope.apps.length === 1 ? '' : 's'}`,
            screen: 'projects',
        },
        {
            label: 'Published Agents',
            value: pubAgents,
            icon: 'agents',
            tone: 'teal',
            sub: `${pubAgents} published · ${scope.agents.length - pubAgents} in progress`,
            screen: 'agents',
        },
        {
            label: 'Available Tools',
            value: stat.tools,
            icon: 'tools',
            tone: 'amber',
            sub: `${clientTools} client-side · ${stat.tools - clientTools} hosted`,
            screen: 'tools',
        },
    ];
    const opCards: {
        label: string;
        value: string;
        icon: string;
        tone: 'purple' | 'orange' | 'teal' | 'red';
        spark?: number[];
        sub?: string;
    }[] = [
        {
            label: 'Agent Runs Today',
            value: stat.runsToday.toLocaleString(),
            icon: 'runs',
            tone: 'purple',
            spark: runsOverTime.slice(-12),
        },
        {
            label: 'Waiting for Client Tool',
            value: String(stat.waitingClient),
            icon: 'clock',
            tone: 'orange',
            sub: 'Pending SDK execution',
        },
        {
            label: 'Successful Runs',
            value: stat.success.toLocaleString(),
            icon: 'checkCircle',
            tone: 'teal',
            sub: '93.3% success rate',
        },
        {
            label: 'Failed Runs',
            value: String(stat.failed),
            icon: 'xCircle',
            tone: 'red',
            sub: "2.4% of today's runs",
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="route-anim">
                <PageHeader
                    title={
                        isAll
                            ? 'Operations Overview'
                            : scope.role.id === 'projadmin'
                              ? 'Project Overview'
                              : 'My Workspace'
                    }
                    sub={
                        isAll
                            ? 'Real-time view of agent activity, tool execution, model usage, and governance across all Milaha applications.'
                            : scope.role.id === 'projadmin'
                              ? `Activity across the ${scope.apps.map((a) => a.name).join(', ')} project${scope.projects.length === 1 ? '' : 's'} you own.`
                              : `Agents and tools in the ${scope.projects.length} project${scope.projects.length === 1 ? '' : 's'} you're a member of.`
                    }
                    actions={
                        <>
                            <Btn variant="default" icon="download" size="md">
                                Export
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="plus"
                                size="md"
                                onClick={() => go('createAgent')}
                            >
                                New Agent
                            </Btn>
                        </>
                    }
                />

                <ScopeBanner scope={scope} />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4, 1fr)',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    {statCards.map((s, i) => (
                        <StatCard
                            key={i}
                            label={s.label}
                            value={s.value}
                            icon={s.icon}
                            tone={s.tone}
                            sub={s.sub}
                            onClick={
                                s.screen
                                    ? () => go(s.screen as RouteName)
                                    : undefined
                            }
                        />
                    ))}
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4, 1fr)',
                        gap: 12,
                        marginBottom: 18,
                    }}
                >
                    {opCards.map((s, i) => (
                        <StatCard
                            key={i}
                            label={s.label}
                            value={s.value}
                            icon={s.icon}
                            tone={s.tone}
                            spark={s.spark}
                            sub={s.sub}
                            onClick={() => go('runs')}
                        />
                    ))}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.6fr 1fr',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    <Card>
                        <SectionHeader
                            title="Agent runs — last 24 hours"
                            sub={
                                isAll
                                    ? 'Hourly run volume across all applications'
                                    : 'Hourly run volume in your scope'
                            }
                            icon="runs"
                            right={
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        alignItems: 'baseline',
                                    }}
                                >
                                    <span
                                        className="tnum"
                                        style={{
                                            fontSize: 22,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {stat.runsToday.toLocaleString()}
                                    </span>
                                    <Badge tone="teal">▲ 12.4%</Badge>
                                </div>
                            }
                        />
                        <AreaSpark
                            values={runsOverTime}
                            height={150}
                            color="var(--primary)"
                        />
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                fontSize: 10.5,
                                color: 'var(--text-3)',
                                marginTop: 6,
                                fontWeight: 500,
                            }}
                        >
                            <span>00:00</span>
                            <span>06:00</span>
                            <span>12:00</span>
                            <span>18:00</span>
                            <span>now</span>
                        </div>
                    </Card>
                    <Card>
                        <SectionHeader
                            title="Run status distribution"
                            sub="Today"
                            icon="layers"
                        />
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 18,
                            }}
                        >
                            <Donut
                                data={runStatus}
                                size={140}
                                thickness={17}
                                centerLabel={runStatus
                                    .reduce((s, x) => s + x.value, 0)
                                    .toLocaleString()}
                                centerSub="runs"
                            />
                            <DonutLegend data={runStatus} />
                        </div>
                    </Card>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr 1.3fr',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    <Card
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'space-between',
                        }}
                    >
                        <SectionHeader
                            title="Estimated token usage"
                            icon="cpu"
                        />
                        <div>
                            <div
                                className="tnum"
                                style={{
                                    fontSize: 30,
                                    fontWeight: 700,
                                    letterSpacing: -0.8,
                                }}
                            >
                                {stat.tokens}
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-3)',
                                    marginBottom: 12,
                                }}
                            >
                                tokens consumed today
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    fontSize: 11.5,
                                }}
                            >
                                <div style={{ flex: 1 }}>
                                    <div
                                        style={{
                                            color: 'var(--text-3)',
                                            marginBottom: 3,
                                        }}
                                    >
                                        Input
                                    </div>
                                    <div
                                        className="tnum"
                                        style={{
                                            fontWeight: 700,
                                            fontSize: 14,
                                        }}
                                    >
                                        {(
                                            parseFloat(stat.tokens) * 0.75
                                        ).toFixed(2)}
                                        M
                                    </div>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div
                                        style={{
                                            color: 'var(--text-3)',
                                            marginBottom: 3,
                                        }}
                                    >
                                        Output
                                    </div>
                                    <div
                                        className="tnum"
                                        style={{
                                            fontWeight: 700,
                                            fontSize: 14,
                                        }}
                                    >
                                        {(
                                            parseFloat(stat.tokens) * 0.25
                                        ).toFixed(2)}
                                        M
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                    <Card
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'space-between',
                        }}
                    >
                        <SectionHeader title="Estimated cost" icon="bolt" />
                        <div>
                            <div
                                className="tnum"
                                style={{
                                    fontSize: 30,
                                    fontWeight: 700,
                                    letterSpacing: -0.8,
                                }}
                            >
                                {stat.cost}
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-3)',
                                    marginBottom: 12,
                                }}
                            >
                                today
                            </div>
                            <Progress
                                value={64}
                                color="var(--primary)"
                                showVal
                            />
                            <div
                                style={{
                                    fontSize: 11,
                                    color: 'var(--text-3)',
                                    marginTop: 6,
                                }}
                            >
                                64% of {isAll ? 'daily' : 'your'} budget
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <SectionHeader
                            title="LLM usage by model"
                            sub={
                                isAll
                                    ? 'Share of runs today'
                                    : 'Runs (7d) in your scope'
                            }
                            icon="llm"
                            right={
                                navAllowed(scope.role.id, 'llm') ? (
                                    <Btn
                                        variant="ghost"
                                        size="sm"
                                        iconRight="arrowRight"
                                        onClick={() => go('llm')}
                                    >
                                        Providers
                                    </Btn>
                                ) : null
                            }
                        />
                        <HBars
                            data={llmData.map((d) => ({
                                ...d,
                                color: 'var(--primary)',
                            }))}
                            valueFmt={
                                isAll
                                    ? (v) => v + '%'
                                    : (v) => v.toLocaleString()
                            }
                            max={llmMax}
                        />
                    </Card>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.5fr 1fr',
                        gap: 12,
                        marginBottom: 12,
                    }}
                >
                    <Card pad={false}>
                        <div style={{ padding: '14px 16px 12px' }}>
                            <SectionHeader
                                title="Recent agent runs"
                                icon="runs"
                                style={{ marginBottom: 0 }}
                                right={
                                    <Btn
                                        variant="ghost"
                                        size="sm"
                                        iconRight="arrowRight"
                                        onClick={() => go('runs')}
                                    >
                                        View all
                                    </Btn>
                                }
                            />
                        </div>
                        {recentRuns.length === 0 ? (
                            <EmptyState
                                icon="runs"
                                title="No runs yet"
                                desc="Runs in your scope will appear here."
                            />
                        ) : (
                            <Table
                                columns={[
                                    { label: 'Run' },
                                    { label: 'Agent' },
                                    { label: 'Status' },
                                    { label: 'Latency', align: 'right' },
                                    { label: 'Cost', align: 'right' },
                                ]}
                            >
                                {recentRuns.map((r) => {
                                    const ag = MAAC.agentById(r.agentId);

                                    return (
                                        <Tr
                                            key={r.id}
                                            onClick={() =>
                                                go('run', { id: r.id })
                                            }
                                        >
                                            <Td mono strong>
                                                {r.id}
                                            </Td>
                                            <Td>
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 7,
                                                    }}
                                                >
                                                    <Badge tone="neutral" soft>
                                                        {r.appId}
                                                    </Badge>
                                                    <span
                                                        style={{
                                                            color: 'var(--text)',
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        {ag?.name.replace(
                                                            ' Agent',
                                                            '',
                                                        )}
                                                    </span>
                                                </div>
                                            </Td>
                                            <Td>
                                                <RunBadge
                                                    status={r.status}
                                                    dot
                                                />
                                            </Td>
                                            <Td align="right" mono>
                                                {r.latency}
                                            </Td>
                                            <Td align="right" mono>
                                                ${r.cost.toFixed(4)}
                                            </Td>
                                        </Tr>
                                    );
                                })}
                            </Table>
                        )}
                    </Card>

                    <Card pad={false}>
                        <div style={{ padding: '14px 16px 12px' }}>
                            <SectionHeader
                                title="Top used agents"
                                icon="agents"
                                style={{ marginBottom: 0 }}
                                right={
                                    <Btn
                                        variant="ghost"
                                        size="sm"
                                        iconRight="arrowRight"
                                        onClick={() => go('agents')}
                                    >
                                        Agents
                                    </Btn>
                                }
                            />
                        </div>
                        <div style={{ padding: '4px 16px 16px' }}>
                            {topAgents.length === 0 ? (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: 'var(--text-3)',
                                        padding: '20px 0',
                                        textAlign: 'center',
                                    }}
                                >
                                    No agents in scope.
                                </div>
                            ) : (
                                <HBars
                                    data={topAgents.map((a, i) => ({
                                        label: a.name.replace(' Agent', ''),
                                        value: a.runs,
                                        color: [
                                            'var(--purple-600)',
                                            'var(--purple-500)',
                                            'var(--teal-500)',
                                            'var(--blue-500)',
                                            'var(--orange-500)',
                                        ][i],
                                    }))}
                                    onClick={(item) => {
                                        const a = topAgents.find(
                                            (x) =>
                                                x.name.replace(' Agent', '') ===
                                                item.label,
                                        );

                                        if (a) {
                                            go('agent', { id: a.id });
                                        }
                                    }}
                                />
                            )}
                        </div>
                    </Card>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.5fr 1fr',
                        gap: 12,
                    }}
                >
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
                                title="Tools requiring implementation"
                                sub="Client-side tools awaiting SDK handlers"
                                icon="link"
                                style={{ marginBottom: 0 }}
                            />
                            <Btn
                                variant="soft"
                                size="sm"
                                iconRight="arrowRight"
                                onClick={() => go('sdk')}
                            >
                                SDK Center
                            </Btn>
                        </div>
                        {toolsNeedImpl.length === 0 ? (
                            <EmptyState
                                icon="check2"
                                title="All tools implemented"
                                desc="No client-side tools are awaiting implementation in your scope."
                            />
                        ) : (
                            <Table
                                columns={[
                                    { label: 'Tool' },
                                    { label: 'Application' },
                                    { label: 'Agent' },
                                    { label: 'Status' },
                                ]}
                            >
                                {toolsNeedImpl.map((t) => (
                                    <Tr
                                        key={t.id}
                                        onClick={() => go('tool', { id: t.id })}
                                    >
                                        <Td mono strong>
                                            {t.name}
                                        </Td>
                                        <Td>
                                            <Badge tone="neutral">
                                                {t.appId}
                                            </Badge>
                                        </Td>
                                        <Td>
                                            {MAAC.agentById(
                                                t.usedBy[0],
                                            )?.name.replace(' Agent', '')}
                                        </Td>
                                        <Td>
                                            <ImplBadge status={t.impl} />
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                        )}
                    </Card>

                    <Card pad={false}>
                        <div style={{ padding: '14px 16px 12px' }}>
                            <SectionHeader
                                title="Security & governance alerts"
                                icon="shield-alert"
                                style={{ marginBottom: 0 }}
                                right={
                                    canGov ? (
                                        <Btn
                                            variant="ghost"
                                            size="sm"
                                            iconRight="arrowRight"
                                            onClick={() => go('governance')}
                                        >
                                            Govern
                                        </Btn>
                                    ) : null
                                }
                            />
                        </div>
                        <div
                            style={{ display: 'flex', flexDirection: 'column' }}
                        >
                            {D.alerts
                                .slice(0, scope.role.id === 'dev' ? 2 : 4)
                                .map((a, i) => (
                                    <div
                                        key={i}
                                        className="maac-row"
                                        onClick={() =>
                                            go(canGov ? 'governance' : 'sdk')
                                        }
                                        style={{
                                            display: 'flex',
                                            gap: 11,
                                            padding: '11px 16px',
                                            borderTop:
                                                '1px solid var(--border)',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 30,
                                                height: 30,
                                                borderRadius: 8,
                                                flexShrink: 0,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background:
                                                    a.sev === 'high'
                                                        ? 'var(--red-100)'
                                                        : a.sev === 'med'
                                                          ? 'var(--orange-100)'
                                                          : 'var(--primary-soft)',
                                                color:
                                                    a.sev === 'high'
                                                        ? 'var(--red-600)'
                                                        : a.sev === 'med'
                                                          ? 'var(--orange-600)'
                                                          : 'var(--primary)',
                                            }}
                                        >
                                            <Icon name={a.icon} size={16} />
                                        </span>
                                        <div style={{ minWidth: 0, flex: 1 }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    justifyContent:
                                                        'space-between',
                                                    gap: 8,
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        fontSize: 12.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {a.title}
                                                </span>
                                                <span
                                                    style={{
                                                        fontSize: 10.5,
                                                        color: 'var(--text-3)',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {a.time}
                                                </span>
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: 'var(--text-3)',
                                                    marginTop: 2,
                                                }}
                                            >
                                                {a.desc}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}
