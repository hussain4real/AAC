/* ============================================================
   MAAC — Governance
   ============================================================ */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties } from 'react';
import {
    approve as approveRequest,
    reject as rejectRequest,
} from '@/actions/App/Http/Controllers/Maac/ApprovalRequestController';
import { ScopeBanner } from '@/components/maac/common';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    EmptyState,
    KV,
    Modal,
    PageHeader,
    SectionHeader,
    SensBadge,
    Table,
    Tabs,
    Td,
    Toggle,
    Tr,
    TONES,
    SENS_TONE,
} from '@/components/maac/ui';
import type { TabDef, Tone } from '@/components/maac/ui';
import type { ApprovalItem, Policy, Role, SensitivityLevel } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';
import type { MaacAuditEvent, MaacOperational } from '@/types/global';

/* ── Local sub-components ─────────────────────────────────── */

type ApprovalBuckets = {
    tools: ApprovalItem[];
    agents: ApprovalItem[];
    models: ApprovalItem[];
    data: ApprovalItem[];
};

const REVIEW_LABEL: CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    color: 'var(--text-3)',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
};

/** Render a tool contract's input/output schema as field → type rows. */
function SchemaList({
    title,
    schema,
}: {
    title: string;
    schema: Record<string, string>;
}) {
    const entries = Object.entries(schema);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div style={{ marginTop: 12 }}>
            <div style={REVIEW_LABEL}>{title}</div>
            <div
                style={{
                    marginTop: 6,
                    border: '1px solid var(--border)',
                    borderRadius: 'var(--r-md)',
                    overflow: 'hidden',
                }}
            >
                {entries.map(([field, type], i) => (
                    <div
                        key={field}
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            padding: '6px 10px',
                            fontSize: 12,
                            borderTop: i ? '1px solid var(--border)' : 'none',
                        }}
                    >
                        <span className="mono">{field}</span>
                        <span
                            className="mono"
                            style={{ color: 'var(--text-3)' }}
                        >
                            {type}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Full 360° detail view of an approval request, shown in the Review modal. */
function ApprovalDetail({ item }: { item: ApprovalItem }) {
    const s = item.subject;
    const requestFields = [
        { k: 'Request type', v: item.type },
        { k: 'Application', v: item.app },
        { k: 'Requested by', v: item.requestedBy },
        ...(item.sensitivity
            ? [{ k: 'Sensitivity', v: item.sensitivity }]
            : []),
        ...(item.env ? [{ k: 'Environment', v: item.env }] : []),
        { k: 'Waiting', v: item.waiting },
    ];

    return (
        <div>
            {item.blockers && item.blockers.length > 0 && (
                <div
                    style={{
                        marginBottom: 14,
                        padding: '10px 12px',
                        borderRadius: 'var(--r-md)',
                        background: 'var(--red-100)',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            marginBottom: 6,
                        }}
                    >
                        <Icon
                            name="shield-alert"
                            size={16}
                            style={{ color: 'var(--red-600)' }}
                        />
                        <span
                            style={{
                                fontSize: 12.5,
                                fontWeight: 700,
                                color: 'var(--red-600)',
                            }}
                        >
                            Cannot approve yet — {item.blockers.length} unmet
                            prerequisite{item.blockers.length === 1 ? '' : 's'}
                        </span>
                    </div>
                    <ul
                        style={{
                            margin: 0,
                            paddingLeft: 18,
                            fontSize: 12,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                        }}
                    >
                        {item.blockers.map((b) => (
                            <li key={b}>{b}</li>
                        ))}
                    </ul>
                </div>
            )}
            <KV cols={1} items={requestFields} />
            {item.summary && (
                <div
                    style={{
                        marginTop: 12,
                        fontSize: 12.5,
                        color: 'var(--text-2)',
                        lineHeight: 1.55,
                    }}
                >
                    {item.summary}
                </div>
            )}
            {s && (
                <div
                    style={{
                        marginTop: 16,
                        paddingTop: 14,
                        borderTop: '1px solid var(--border)',
                    }}
                >
                    <SectionHeader title={`${s.kind} detail`} icon="info" />
                    <KV cols={1} items={s.fields} />
                    {s.description && (
                        <div
                            style={{
                                marginTop: 10,
                                fontSize: 12.5,
                                color: 'var(--text-2)',
                                lineHeight: 1.55,
                            }}
                        >
                            {s.description}
                        </div>
                    )}
                    {s.systemPrompt && (
                        <div style={{ marginTop: 12 }}>
                            <div style={REVIEW_LABEL}>System prompt</div>
                            <pre
                                className="mono"
                                style={{
                                    marginTop: 6,
                                    fontSize: 11.5,
                                    color: 'var(--text-2)',
                                    background: 'var(--surface-3)',
                                    border: '1px solid var(--border)',
                                    borderRadius: 'var(--r-md)',
                                    padding: '10px 12px',
                                    whiteSpace: 'pre-wrap',
                                    maxHeight: 200,
                                    overflowY: 'auto',
                                }}
                            >
                                {s.systemPrompt}
                            </pre>
                        </div>
                    )}
                    {s.tools && s.tools.length > 0 && (
                        <div style={{ marginTop: 12 }}>
                            <div style={REVIEW_LABEL}>Assigned tools</div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 6,
                                    marginTop: 6,
                                }}
                            >
                                {s.tools.map((t) => (
                                    <Badge key={t} tone="neutral" soft>
                                        {t}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}
                    {s.inputSchema && (
                        <SchemaList
                            title="Input schema"
                            schema={s.inputSchema}
                        />
                    )}
                    {s.outputSchema && (
                        <SchemaList
                            title="Output schema"
                            schema={s.outputSchema}
                        />
                    )}
                </div>
            )}
        </div>
    );
}

function ApprovalQueues({ A }: { A: ApprovalBuckets }) {
    const { currentTeam } = usePage().props;
    const [reviewing, setReviewing] = useState<ApprovalItem | null>(null);

    const decide = (id: string, decision: 'approve' | 'reject') => {
        if (!currentTeam) {
            return;
        }

        const target = decision === 'approve' ? approveRequest : rejectRequest;
        router.post(
            target([currentTeam.slug, id]).url,
            {},
            { preserveScroll: true },
        );
        setReviewing(null);
    };

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
        <>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
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
                                style={{
                                    fontSize: 13,
                                    fontWeight: 700,
                                    flex: 1,
                                }}
                            >
                                {q.title}
                            </span>
                            <Badge tone={q.tone}>
                                {q.items.length} pending
                            </Badge>
                        </div>
                        <div
                            style={{ display: 'flex', flexDirection: 'column' }}
                        >
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
                                                    q.key === 'tools'
                                                        ? 'mono'
                                                        : ''
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
                                            disabled={
                                                (it.blockers?.length ?? 0) > 0
                                            }
                                            onClick={() =>
                                                decide(it.id, 'approve')
                                            }
                                        >
                                            Approve
                                        </Btn>
                                        <Btn
                                            variant="default"
                                            size="sm"
                                            onClick={() => setReviewing(it)}
                                        >
                                            Review
                                        </Btn>
                                        <Btn
                                            variant="ghost"
                                            size="sm"
                                            style={{ color: 'var(--red-600)' }}
                                            onClick={() =>
                                                decide(it.id, 'reject')
                                            }
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
            {reviewing && (
                <Modal
                    open
                    onClose={() => setReviewing(null)}
                    title={reviewing.title}
                    sub={`${reviewing.type} · ${reviewing.app}`}
                    icon="shield"
                    width={640}
                    footer={
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                justifyContent: 'flex-end',
                            }}
                        >
                            <Btn
                                variant="ghost"
                                size="md"
                                style={{ color: 'var(--red-600)' }}
                                onClick={() => decide(reviewing.id, 'reject')}
                            >
                                Reject
                            </Btn>
                            <Btn
                                variant="primary"
                                size="md"
                                icon="check"
                                disabled={(reviewing.blockers?.length ?? 0) > 0}
                                onClick={() => decide(reviewing.id, 'approve')}
                            >
                                Approve
                            </Btn>
                        </div>
                    }
                >
                    <ApprovalDetail item={reviewing} />
                </Modal>
            )}
        </>
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
                    <SectionHeader title="Retention & audit" icon="eye" />
                    <KV
                        cols={1}
                        items={[
                            {
                                k: 'Audit log retention',
                                v: `${MAAC.governanceSettings.auditRetentionDays} days`,
                            },
                            {
                                k: 'Prompt / response retention',
                                v: `${MAAC.governanceSettings.retainPromptsDays} / ${MAAC.governanceSettings.retainResponsesDays} days`,
                            },
                            {
                                k: 'Tool arg / result retention',
                                v: `${MAAC.governanceSettings.retainToolArgumentsDays} / ${MAAC.governanceSettings.retainToolResultsDays} days`,
                            },
                            {
                                k: 'Trace access',
                                v: 'Security Reviewer + Auditor',
                            },
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

function OperationalMetrics({ m }: { m: MaacOperational }) {
    const tiles: { label: string; value: string; color: string }[] = [
        {
            label: 'Error rate',
            value: `${m.errorRate}%`,
            color: m.errorRate > 5 ? 'var(--red-600)' : 'var(--teal-600)',
        },
        {
            label: 'Tool failure rate',
            value: `${m.toolFailureRate}%`,
            color: m.toolFailureRate > 5 ? 'var(--red-600)' : 'var(--teal-600)',
        },
        {
            label: 'Waiting runs',
            value: String(m.waitingRuns),
            color: 'var(--orange-600)',
        },
        {
            label: 'Expired runs',
            value: String(m.expiredRuns),
            color: 'var(--amber-600)',
        },
        {
            label: 'Avg latency',
            value: `${(m.avgLatencyMs / 1000).toFixed(1)}s`,
            color: 'var(--blue-600)',
        },
        {
            label: 'Runs (7d)',
            value: String(m.totalRuns),
            color: 'var(--primary)',
        },
    ];

    return (
        <div>
            {m.costAnomaly && (
                <Card
                    style={{
                        marginBottom: 12,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        background: 'var(--red-100)',
                    }}
                >
                    <Icon
                        name="bolt"
                        size={18}
                        style={{ color: 'var(--red-600)' }}
                    />
                    <span style={{ fontSize: 12.5, fontWeight: 600 }}>
                        Cost anomaly detected — today's spend is well above the
                        7-day average.
                    </span>
                </Card>
            )}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(6, 1fr)',
                    gap: 12,
                }}
            >
                {tiles.map((t) => (
                    <Card key={t.label}>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--text-3)',
                                marginBottom: 6,
                            }}
                        >
                            {t.label}
                        </div>
                        <div
                            className="tnum"
                            style={{
                                fontSize: 22,
                                fontWeight: 700,
                                color: t.color,
                            }}
                        >
                            {t.value}
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}

function AuditLog({
    events,
    operational,
}: {
    events: MaacAuditEvent[];
    operational: MaacOperational;
}) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <OperationalMetrics m={operational} />
            <Card pad={false}>
                <div style={{ padding: '14px 16px 12px' }}>
                    <SectionHeader
                        title="Audit log"
                        sub="Administrative and runtime governance events"
                        icon="eye"
                        style={{ marginBottom: 0 }}
                    />
                </div>
                {events.length === 0 ? (
                    <EmptyState
                        icon="eye"
                        title="No audit events"
                        desc="Administrative changes and governance decisions will appear here."
                    />
                ) : (
                    <Table
                        columns={[
                            { label: 'Action' },
                            { label: 'Actor' },
                            { label: 'Target' },
                            { label: 'Environment' },
                            { label: 'When', align: 'right' },
                        ]}
                    >
                        {events.map((e) => (
                            <Tr key={e.id}>
                                <Td strong>{e.label}</Td>
                                <Td>{e.actor}</Td>
                                <Td>{e.target ?? '—'}</Td>
                                <Td>
                                    {e.environment ? (
                                        <Badge tone="neutral">
                                            {e.environment}
                                        </Badge>
                                    ) : (
                                        '—'
                                    )}
                                </Td>
                                <Td align="right" mono>
                                    {e.at ?? e.time}
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}
            </Card>
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
              { id: 'audit', label: 'Audit Log', icon: 'eye' },
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
                {tab === 'audit' && (
                    <AuditLog
                        events={MAAC.auditEvents}
                        operational={MAAC.operational}
                    />
                )}
            </div>
        </>
    );
}
