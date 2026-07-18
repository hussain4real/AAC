/* ============================================================
   MAACC — Dashboard (role/scope aware operations overview)
   ============================================================ */
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AreaSpark,
    Donut,
    DonutLegend,
    HBars,
    StatCard,
} from '@/components/maacc/charts';
import { ScopeBanner } from '@/components/maacc/common';
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
} from '@/components/maacc/ui';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { effectiveImpl } from '@/maacc/data';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import type { RouteName } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';
import type { DashboardInvitation } from '@/types';

type Props = { pendingInvitations?: DashboardInvitation[] };

export default function Dashboard({ pendingInvitations = [] }: Props) {
    const MAACC = useMaaccData();
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const { go, scope } = useMaaccNav();
    const authorizedNavigation = usePage().props.auth.maacc.navigation;
    const isAll = scope.isAll;
    const D = MAACC.dashboard;
    const gstat = D.stats;

    const activeApps = scope.apps.filter((a) => a.status === 'Active').length;
    const suspApps = scope.apps.filter((a) => a.status !== 'Active').length;
    const pubAgents = scope.agents.filter(
        (a) => a.status === 'Published',
    ).length;
    const clientTools = scope.tools.filter(
        (t) => t.execMode === 'client',
    ).length;

    // The all-applications view uses the team-wide rollups computed server-side
    // (today's window). A scoped view derives its run stats from the real runs
    // visible in scope, so the numbers are always genuine, never estimated.
    const periodLabel = isAll ? 'today' : 'in scope';
    const runsToday = isAll ? gstat.runsToday : scope.runs.length;
    const scaleVsAll = gstat.runsToday > 0 ? runsToday / gstat.runsToday : 0;
    const scopedTokens = scope.runs.reduce(
        (sum, r) => sum + r.tokensIn + r.tokensOut,
        0,
    );
    const scopedCost = scope.runs.reduce((sum, r) => sum + r.cost, 0);
    const compact = (n: number): string =>
        Intl.NumberFormat('en', {
            notation: 'compact',
            maximumFractionDigits: 2,
        }).format(n);

    const stat = isAll
        ? gstat
        : {
              apps: scope.apps.length,
              projects: scope.projects.length,
              agents: scope.agents.length,
              tools: scope.tools.length,
              runsToday,
              waitingClient: scope.runs.filter(
                  (r) => r.status === 'waiting_for_client',
              ).length,
              success: scope.runs.filter((r) => r.status === 'completed')
                  .length,
              failed: scope.runs.filter((r) =>
                  ['failed', 'expired'].includes(r.status),
              ).length,
              tokens: compact(scopedTokens),
              cost: 'QAR ' + compact(scopedCost),
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
                  value: stat.waitingClient,
                  color: 'var(--orange-600)',
              },
              {
                  label: 'Running',
                  value: scope.runs.filter((r) => r.status === 'running')
                      .length,
                  color: 'var(--blue-500)',
              },
              {
                  label: 'Failed',
                  value: stat.failed,
                  color: 'var(--red-500)',
              },
          ];
    const runsOverTime = isAll
        ? D.runsOverTime
        : D.runsOverTime.map((v) => Math.round(v * scaleVsAll));

    // Run-volume trend: recent 12h vs the prior 12h of the 24-bucket series.
    const recentVol = runsOverTime.slice(12).reduce((s, v) => s + v, 0);
    const earlierVol = runsOverTime.slice(0, 12).reduce((s, v) => s + v, 0);
    const trendPct =
        earlierVol > 0 ? ((recentVol - earlierVol) / earlierVol) * 100 : null;

    const successRate =
        stat.runsToday > 0 ? (stat.success / stat.runsToday) * 100 : 0;
    const failRate =
        stat.runsToday > 0 ? (stat.failed / stat.runsToday) * 100 : 0;

    const recentRuns = (isAll ? MAACC.runs : scope.runs).slice(0, 6);
    const topAgents = [...scope.agents]
        .sort((a, b) => b.runs7d - a.runs7d)
        .slice(0, 5)
        .map((a) => ({ id: a.id, name: a.name, runs: a.runs7d, app: a.appId }));
    const toolsNeedImpl = scope.tools.filter((t) =>
        ['required', 'outdated', 'incompatible'].includes(effectiveImpl(t)),
    );

    const llmData = (() => {
        if (isAll) {
            return MAACC.llms
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
                label: MAACC.llmById(id)?.name ?? id,
                value: v,
            }));
    })();
    const llmMax = isAll ? 40 : Math.max(...llmData.map((d) => d.value), 1);

    const canGov = authorizedNavigation.includes('governance');

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
            screen: authorizedNavigation.includes('applications')
                ? 'applications'
                : null,
        },
        {
            label: 'Active Projects',
            value: stat.projects,
            icon: 'projects',
            tone: 'blue',
            sub: `Across ${stat.apps} application${stat.apps === 1 ? '' : 's'}`,
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
            label: isAll ? 'Agent Runs Today' : 'Agent Runs',
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
            sub: `${successRate.toFixed(1)}% success rate`,
        },
        {
            label: 'Failed Runs',
            value: String(stat.failed),
            icon: 'xCircle',
            tone: 'red',
            sub: `${failRate.toFixed(1)}% of runs ${periodLabel}`,
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
                                    {trendPct !== null && (
                                        <Badge
                                            tone={
                                                trendPct >= 0 ? 'teal' : 'red'
                                            }
                                        >
                                            {trendPct >= 0 ? '▲' : '▼'}{' '}
                                            {Math.abs(trendPct).toFixed(1)}%
                                        </Badge>
                                    )}
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
                                tokens consumed {periodLabel}
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
                                {periodLabel}
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
                                authorizedNavigation.includes('llm') ? (
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
                                    const ag = MAACC.agentById(r.agentId);

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
                                            {MAACC.agentById(
                                                t.usedBy[0],
                                            )?.name.replace(' Agent', '')}
                                        </Td>
                                        <Td>
                                            <ImplBadge
                                                status={effectiveImpl(t)}
                                            />
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
                                        className="maacc-row"
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
