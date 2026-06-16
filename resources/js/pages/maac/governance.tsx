/* ============================================================
   MAAC — Governance
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ScopeBanner } from '@/components/maac/common';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    KV,
    PageHeader,
    SectionHeader,
    SensBadge,
    Tabs,
    Toggle,
    TONES,
    SENS_TONE,
} from '@/components/maac/ui';
import type { TabDef, Tone } from '@/components/maac/ui';
import type { ApprovalItem, Policy, Role, SensitivityLevel } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ── Local sub-components ─────────────────────────────────── */

type ApprovalBuckets = {
    tools: ApprovalItem[];
    agents: ApprovalItem[];
    models: ApprovalItem[];
    data: ApprovalItem[];
};

function ApprovalQueues({ A }: { A: ApprovalBuckets }) {
    const queues: {
        key: keyof ApprovalBuckets;
        title: string;
        icon: string;
        tone: Tone;
        items: ApprovalItem[];
    }[] = [
        {
            key: 'tools',
            title: 'Tools requiring approval',
            icon: 'tools',
            tone: 'orange',
            items: A.tools,
        },
        {
            key: 'agents',
            title: 'Agents awaiting publication',
            icon: 'agents',
            tone: 'blue',
            items: A.agents,
        },
        {
            key: 'models',
            title: 'Models pending approval',
            icon: 'llm',
            tone: 'purple',
            items: A.models,
        },
        {
            key: 'data',
            title: 'Sensitive data access requests',
            icon: 'lock',
            tone: 'red',
            items: A.data,
        },
    ];

    return (
        <div
            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
        >
            {queues.map((q) => (
                <Card key={q.key} pad={false}>
                    <div
                        style={{
                            padding: '13px 16px 11px',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            borderBottom: '1px solid var(--border)',
                        }}
                    >
                        <span
                            style={{
                                width: 30,
                                height: 30,
                                borderRadius: 8,
                                background: TONES[q.tone].bg,
                                color: TONES[q.tone].fg,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <Icon name={q.icon} size={16} />
                        </span>
                        <span
                            style={{ fontSize: 13, fontWeight: 700, flex: 1 }}
                        >
                            {q.title}
                        </span>
                        <Badge tone={q.tone}>{q.items.length} pending</Badge>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        {q.items.map((it, i) => (
                            <div
                                key={it.id}
                                style={{
                                    padding: '12px 16px',
                                    borderTop: i
                                        ? '1px solid var(--border)'
                                        : 'none',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        justifyContent: 'space-between',
                                        gap: 10,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div
                                            className={
                                                q.key === 'tools' ? 'mono' : ''
                                            }
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 600,
                                            }}
                                        >
                                            {it.title}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: 'var(--text-3)',
                                                marginTop: 3,
                                            }}
                                        >
                                            {it.type} · {it.app}
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                                marginTop: 7,
                                            }}
                                        >
                                            <Avatar
                                                name={it.requestedBy}
                                                size={18}
                                            />
                                            <span
                                                style={{
                                                    fontSize: 11.5,
                                                    color: 'var(--text-2)',
                                                }}
                                            >
                                                {it.requestedBy}
                                            </span>
                                            {it.sensitivity && (
                                                <SensBadge
                                                    level={it.sensitivity}
                                                />
                                            )}
                                            {it.env && (
                                                <Badge tone="neutral">
                                                    {it.env}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <span
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--text-3)',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {it.waiting}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        marginTop: 11,
                                    }}
                                >
                                    <Btn
                                        variant="primary"
                                        size="sm"
                                        icon="check"
                                    >
                                        Approve
                                    </Btn>
                                    <Btn variant="default" size="sm">
                                        Review
                                    </Btn>
                                    <Btn
                                        variant="ghost"
                                        size="sm"
                                        style={{ color: 'var(--red-600)' }}
                                    >
                                        Reject
                                    </Btn>
                                </div>
                            </div>
                        ))}
                        {q.items.length === 0 && (
                            <div
                                style={{
                                    padding: '22px 16px',
                                    textAlign: 'center',
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                }}
                            >
                                Queue is empty 🎉
                            </div>
                        )}
                    </div>
                </Card>
            ))}
        </div>
    );
}

function RolesPerms() {
    const MAAC = useMaacData();

    return (
        <div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(330px, 1fr))',
                    gap: 12,
                }}
            >
                {(MAAC.roles as Role[]).map((r) => (
                    <Card key={r.name}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                marginBottom: 9,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <span
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: 'var(--primary-soft)',
                                        color: 'var(--primary)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <Icon name="user" size={17} />
                                </span>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {r.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {r.users} users
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: 'var(--text-2)',
                                lineHeight: 1.5,
                                marginBottom: 11,
                                minHeight: 36,
                            }}
                        >
                            {r.desc}
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                flexWrap: 'wrap',
                                gap: 6,
                            }}
                        >
                            {r.perms.map((p) => (
                                <Badge key={p} tone="neutral" soft>
                                    {p}
                                </Badge>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}

function PolicyRow({ policy, border }: { policy: Policy; border: boolean }) {
    const [on, setOn] = useState(policy.on);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                padding: '14px 16px',
                borderTop: border ? '1px solid var(--border)' : 'none',
            }}
        >
            <span
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: 8,
                    background: on ? 'var(--teal-100)' : 'var(--surface-3)',
                    color: on ? 'var(--teal-600)' : 'var(--text-3)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                <Icon name={on ? 'lock' : 'power'} size={16} />
            </span>
            <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>
                    {policy.name}
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: 'var(--text-3)',
                        marginTop: 2,
                    }}
                >
                    {policy.desc}
                </div>
            </div>
            <Toggle on={on} onChange={setOn} />
        </div>
    );
}

function SecurityPolicies() {
    const MAAC = useMaacData();

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <Card pad={false}>
                <div style={{ padding: '14px 16px' }}>
                    <SectionHeader
                        title="Platform security policies"
                        icon="shield"
                        style={{ marginBottom: 0 }}
                    />
                </div>
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                    {(MAAC.policies as Policy[]).map((p, i) => (
                        <PolicyRow key={p.name} policy={p} border={i > 0} />
                    ))}
                </div>
            </Card>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card
                    style={{
                        background:
                            'linear-gradient(150deg, var(--primary-soft), transparent)',
                    }}
                >
                    <SectionHeader
                        title="Data isolation guarantee"
                        icon="lock"
                    />
                    <div
                        style={{
                            fontSize: 12.5,
                            color: 'var(--text-2)',
                            lineHeight: 1.6,
                        }}
                    >
                        MAAC <b>never</b> holds credentials for, or directly
                        queries, application production databases. Client-side
                        tools execute inside the owning application's boundary,
                        enforcing that application's own permissions.
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                            marginTop: 13,
                            padding: '9px 12px',
                            background: 'var(--teal-100)',
                            borderRadius: 'var(--r-md)',
                        }}
                    >
                        <Icon
                            name="check2"
                            size={17}
                            style={{ color: 'var(--teal-600)' }}
                        />
                        <span
                            style={{
                                fontSize: 12.5,
                                fontWeight: 600,
                                color: 'var(--teal-600)',
                            }}
                        >
                            Enforced platform-wide
                        </span>
                    </div>
                </Card>
                <Card>
                    <SectionHeader title="Audit access" icon="eye" />
                    <KV
                        cols={1}
                        items={[
                            { k: 'Audit log retention', v: '365 days' },
                            {
                                k: 'Trace access',
                                v: 'Security Reviewer + Auditor',
                            },
                            { k: 'Export', v: 'SIEM-compatible (JSON)' },
                        ]}
                    />
                </Card>
            </div>
        </div>
    );
}

function DataSensitivity() {
    const MAAC = useMaacData();

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 12,
            }}
        >
            {(MAAC.sensitivityLevels as SensitivityLevel[]).map((s) => (
                <Card
                    key={s.name}
                    style={{
                        borderTop: `3px solid ${TONES[SENS_TONE[s.name]].fg}`,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            marginBottom: 10,
                        }}
                    >
                        <SensBadge level={s.name} />
                        <Icon
                            name={
                                s.name === 'Restricted'
                                    ? 'lock'
                                    : s.name === 'Confidential'
                                      ? 'shield'
                                      : s.name === 'Internal'
                                        ? 'building'
                                        : 'globe'
                            }
                            size={18}
                            style={{ color: TONES[SENS_TONE[s.name]].fg }}
                        />
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: 'var(--text-2)',
                            lineHeight: 1.55,
                            minHeight: 64,
                        }}
                    >
                        {s.desc}
                    </div>
                    <div
                        style={{
                            marginTop: 11,
                            paddingTop: 11,
                            borderTop: '1px solid var(--border)',
                            fontSize: 11.5,
                            color: 'var(--text-3)',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                marginBottom: 5,
                            }}
                        >
                            <span>Logging</span>
                            <span
                                style={{
                                    fontWeight: 600,
                                    color: 'var(--text-2)',
                                }}
                            >
                                {s.name === 'Restricted'
                                    ? 'Blocked'
                                    : s.name === 'Confidential'
                                      ? 'Masked'
                                      : 'Allowed'}
                            </span>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                            }}
                        >
                            <span>Models</span>
                            <span
                                style={{
                                    fontWeight: 600,
                                    color: 'var(--text-2)',
                                }}
                            >
                                {s.name === 'Restricted'
                                    ? 'On-prem'
                                    : s.name === 'Confidential'
                                      ? 'Approved'
                                      : 'Any'}
                            </span>
                        </div>
                    </div>
                </Card>
            ))}
        </div>
    );
}

/* ── Page ─────────────────────────────────────────────────── */

export default function Governance() {
    const { scope } = useMaacNav();
    const MAAC = useMaacData();
    const isAdmin = scope.isAll;
    const appNames = new Set(scope.apps.map((a) => a.name).concat('Platform'));
    const filt = (items: ApprovalItem[]) =>
        isAdmin ? items : items.filter((it) => appNames.has(it.app));
    const A: ApprovalBuckets = {
        tools: filt(MAAC.approvals.tools),
        agents: filt(MAAC.approvals.agents),
        models: isAdmin ? MAAC.approvals.models : [],
        data: filt(MAAC.approvals.data),
    };
    const totalPending =
        A.tools.length + A.agents.length + A.models.length + A.data.length;
    const [tab, setTab] = useState('approvals');
    const tabs: TabDef[] = isAdmin
        ? [
              {
                  id: 'approvals',
                  label: 'Approval Queues',
                  icon: 'check2',
                  count: totalPending,
              },
              { id: 'roles', label: 'Roles & Permissions', icon: 'user' },
              { id: 'policies', label: 'Security Policies', icon: 'shield' },
              { id: 'sensitivity', label: 'Data Sensitivity', icon: 'lock' },
          ]
        : [
              {
                  id: 'approvals',
                  label: 'Approval Queues',
                  icon: 'check2',
                  count: totalPending,
              },
              { id: 'sensitivity', label: 'Data Sensitivity', icon: 'lock' },
          ];

    return (
        <>
            <Head title="Governance" />
            <div className="route-anim">
                <PageHeader
                    title="Governance"
                    sub={
                        isAdmin
                            ? 'Roles, approval workflows, security policies, and data sensitivity controls that keep enterprise AI safe and auditable.'
                            : `Approval queues and policies for the ${scope.apps.map((a) => a.name).join(', ')} you own.`
                    }
                    tabs={<Tabs tabs={tabs} active={tab} onChange={setTab} />}
                />
                {!isAdmin && <ScopeBanner scope={scope} />}
                {tab === 'approvals' && <ApprovalQueues A={A} />}
                {tab === 'roles' && <RolesPerms />}
                {tab === 'policies' && <SecurityPolicies />}
                {tab === 'sensitivity' && <DataSensitivity />}
            </div>
        </>
    );
}
