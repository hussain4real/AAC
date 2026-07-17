/* ============================================================
   MAACC — Governance
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties } from 'react';
import {
    approve as approveRequest,
    reject as rejectRequest,
    store as requestApproval,
} from '@/actions/App/Http/Controllers/Maacc/ApprovalRequestController';
import { update as updateGovernanceSettings } from '@/actions/App/Http/Controllers/Maacc/GovernanceSettingController';
import {
    destroy as destroyQuota,
    store as storeQuota,
    update as updateQuota,
} from '@/actions/App/Http/Controllers/Maacc/QuotaLimitController';
import { ScopeBanner } from '@/components/maacc/common';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    EmptyState,
    Field,
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
    Toggle,
    Tr,
    TONES,
    SENS_TONE,
} from '@/components/maacc/ui';
import type { TabDef, Tone } from '@/components/maacc/ui';
import type { ApprovalItem, Role, SensitivityLevel } from '@/maacc/data';
import {
    APPROVAL_TYPE_OPTIONS,
    ENV_OPTIONS,
    FieldError,
    QUOTA_SCOPE_OPTIONS,
    toEnumValue,
} from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';
import type {
    MaaccAuditEvent,
    MaaccOperational,
    MaaccQuota,
} from '@/types/global';

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
    const [showRequest, setShowRequest] = useState(false);

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
                    display: 'flex',
                    justifyContent: 'flex-end',
                    marginBottom: 12,
                }}
            >
                <Btn
                    variant="default"
                    icon="plus"
                    onClick={() => setShowRequest(true)}
                >
                    Request approval
                </Btn>
            </div>
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
            <RequestApprovalModal
                open={showRequest}
                onClose={() => setShowRequest(false)}
            />
        </>
    );
}

function RolesPerms() {
    const MAACC = useMaaccData();

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
                {(MAACC.roles as Role[]).map((r) => (
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

type ToggleKey =
    | 'mask_sensitive_inputs'
    | 'mask_sensitive_outputs'
    | 'block_restricted_logging';
type RetentionKey =
    | 'retain_prompts_days'
    | 'retain_responses_days'
    | 'retain_tool_arguments_days'
    | 'retain_tool_results_days'
    | 'audit_retention_days';

function SecurityPolicies() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const g = MAACC.governanceSettings;

    const form = useForm<{
        mask_sensitive_inputs: boolean;
        mask_sensitive_outputs: boolean;
        tool_result_handling: 'store' | 'mask' | 'exclude';
        block_restricted_logging: boolean;
        retain_prompts_days: number;
        retain_responses_days: number;
        retain_tool_arguments_days: number;
        retain_tool_results_days: number;
        audit_retention_days: number;
        default_daily_run_quota: number | null;
    }>({
        mask_sensitive_inputs: g.maskSensitiveInputs,
        mask_sensitive_outputs: g.maskSensitiveOutputs,
        tool_result_handling: g.toolResultHandling,
        block_restricted_logging: g.blockRestrictedLogging,
        retain_prompts_days: g.retainPromptsDays,
        retain_responses_days: g.retainResponsesDays,
        retain_tool_arguments_days: g.retainToolArgumentsDays,
        retain_tool_results_days: g.retainToolResultsDays,
        audit_retention_days: g.auditRetentionDays,
        default_daily_run_quota: g.defaultDailyRunQuota,
    });

    const save = () => {
        if (!currentTeam) {
            return;
        }

        form.put(updateGovernanceSettings([currentTeam.slug]).url, {
            preserveScroll: true,
        });
    };

    const toggles: { key: ToggleKey; label: string; desc: string }[] = [
        {
            key: 'mask_sensitive_inputs',
            label: 'Mask sensitive inputs',
            desc: 'Redact Confidential+ run inputs before they are stored.',
        },
        {
            key: 'mask_sensitive_outputs',
            label: 'Mask sensitive model outputs',
            desc: 'Redact Confidential+ model responses at rest.',
        },
        {
            key: 'block_restricted_logging',
            label: 'Block restricted logging',
            desc: 'Never write Restricted payloads to logs, even masked.',
        },
    ];
    const retention: { key: RetentionKey; label: string }[] = [
        { key: 'retain_prompts_days', label: 'Prompt retention (days)' },
        { key: 'retain_responses_days', label: 'Response retention (days)' },
        {
            key: 'retain_tool_arguments_days',
            label: 'Tool argument retention (days)',
        },
        {
            key: 'retain_tool_results_days',
            label: 'Tool result retention (days)',
        },
        { key: 'audit_retention_days', label: 'Audit log retention (days)' },
    ];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <Card pad={false}>
                <div
                    style={{
                        padding: '14px 16px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        borderBottom: '1px solid var(--border)',
                    }}
                >
                    <SectionHeader
                        title="Data governance settings"
                        sub="Masking, restricted-logging, and retention windows"
                        icon="shield"
                        style={{ marginBottom: 0 }}
                    />
                    <Btn
                        variant="primary"
                        size="sm"
                        icon="check"
                        disabled={form.processing}
                        onClick={save}
                    >
                        Save
                    </Btn>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                    {toggles.map((t, i) => (
                        <div
                            key={t.key}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 14,
                                padding: '14px 16px',
                                borderTop: i
                                    ? '1px solid var(--border)'
                                    : 'none',
                            }}
                        >
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 13, fontWeight: 600 }}>
                                    {t.label}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--text-3)',
                                        marginTop: 2,
                                    }}
                                >
                                    {t.desc}
                                </div>
                            </div>
                            <Toggle
                                on={form.data[t.key]}
                                onChange={(v) => form.setData(t.key, v)}
                            />
                        </div>
                    ))}
                </div>
                <div
                    style={{
                        padding: '16px',
                        borderTop: '1px solid var(--border)',
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field
                        label="Tool result handling"
                        hint="Exclude prevents tool results from entering retained records or outbound copies."
                    >
                        <Select
                            value={form.data.tool_result_handling}
                            onChange={(value) =>
                                form.setData(
                                    'tool_result_handling',
                                    value as 'store' | 'mask' | 'exclude',
                                )
                            }
                            options={[
                                { value: 'store', label: 'Store' },
                                { value: 'mask', label: 'Mask' },
                                { value: 'exclude', label: 'Exclude' },
                            ]}
                        />
                        <FieldError error={form.errors.tool_result_handling} />
                    </Field>
                    {retention.map((r) => (
                        <Field key={r.key} label={r.label} required>
                            <Input
                                type="number"
                                min="1"
                                max="3650"
                                value={form.data[r.key]}
                                onChange={(e) =>
                                    form.setData(
                                        r.key,
                                        parseInt(e.target.value, 10) || 0,
                                    )
                                }
                            />
                            <FieldError error={form.errors[r.key]} />
                        </Field>
                    ))}
                    <Field
                        label="Default daily run quota"
                        hint="Blank for no platform-wide default."
                    >
                        <Input
                            type="number"
                            min="1"
                            value={form.data.default_daily_run_quota ?? ''}
                            onChange={(e) =>
                                form.setData(
                                    'default_daily_run_quota',
                                    e.target.value === ''
                                        ? null
                                        : parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                        <FieldError
                            error={form.errors.default_daily_run_quota}
                        />
                    </Field>
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
                        MAACC <b>never</b> holds credentials for, or directly
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
            </div>
        </div>
    );
}

function DataSensitivity() {
    const MAACC = useMaaccData();

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 12,
            }}
        >
            {(MAACC.sensitivityLevels as SensitivityLevel[]).map((s) => (
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

function OperationalMetrics({ m }: { m: MaaccOperational }) {
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
    events: MaaccAuditEvent[];
    operational: MaaccOperational;
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

function QuotaFormModal({
    quota,
    open,
    onClose,
}: {
    quota?: MaaccQuota;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const MAACC = useMaaccData();
    const isEdit = !!quota;
    const form = useForm<{
        scope: string;
        subject_id: string;
        environment: string;
        max_runs_per_day: number | null;
        max_tokens_per_day: number | null;
        enabled: boolean;
    }>({
        scope: quota?.scopeKey ?? 'platform',
        subject_id: quota?.subjectId ?? '',
        environment: quota
            ? quota.environment === 'All'
                ? 'all'
                : toEnumValue(quota.environment)
            : 'all',
        max_runs_per_day: quota?.maxRunsPerDay ?? null,
        max_tokens_per_day: quota?.maxTokensPerDay ?? null,
        enabled: quota?.enabled ?? true,
    });

    // Non-platform scopes match a run by the subject's UUID (QuotaGuard), so
    // pick the real record rather than free-typing a slug that never matches.
    const subjectOptionsFor = (scope: string) => {
        switch (scope) {
            case 'application':
                return MAACC.apps.map((a) => ({
                    value: a.uuid ?? a.id,
                    label: a.name,
                }));
            case 'project':
                return MAACC.projects.map((p) => ({
                    value: p.uuid ?? p.id,
                    label: p.name,
                }));
            case 'agent':
                return MAACC.agents.map((a) => ({
                    value: a.uuid ?? a.id,
                    label: a.name,
                }));
            case 'model':
                return MAACC.llms.map((l) => ({
                    value: l.uuid ?? l.id,
                    label: l.name,
                }));
            default:
                return [];
        }
    };
    const requiresSubject = form.data.scope !== 'platform';
    const subjectOptions = subjectOptionsFor(form.data.scope);

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const setScope = (scope: string) => {
        form.setData('scope', scope);
        const options = subjectOptionsFor(scope);
        form.setData(
            'subject_id',
            scope === 'platform' ? '' : (options[0]?.value ?? ''),
        );
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.transform((data) => ({
            ...data,
            environment: data.environment === 'all' ? '' : data.environment,
        }));

        if (quota) {
            form.put(updateQuota([currentTeam.slug, quota.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeQuota([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const envOptions = [
        { value: 'all', label: 'All environments' },
        ...ENV_OPTIONS,
    ];
    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="bolt"
            title={isEdit ? 'Edit quota' : 'New rate limit'}
            sub="Cap daily runs or tokens by scope and environment."
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {isEdit ? 'Save changes' : 'Create quota'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div style={half}>
                    <Field label="Scope" required>
                        <Select
                            value={form.data.scope}
                            onChange={setScope}
                            options={QUOTA_SCOPE_OPTIONS}
                        />
                        <FieldError error={form.errors.scope} />
                    </Field>
                    <Field label="Environment">
                        <Select
                            value={form.data.environment}
                            onChange={(v) => form.setData('environment', v)}
                            options={envOptions}
                        />
                        <FieldError error={form.errors.environment} />
                    </Field>
                </div>
                {requiresSubject && (
                    <Field
                        label="Subject"
                        required
                        hint={`Which ${form.data.scope} this quota limits.`}
                    >
                        <Select
                            value={form.data.subject_id}
                            onChange={(v) => form.setData('subject_id', v)}
                            options={subjectOptions}
                        />
                        <FieldError error={form.errors.subject_id} />
                    </Field>
                )}
                <div style={half}>
                    <Field label="Max runs / day">
                        <Input
                            type="number"
                            min="1"
                            value={form.data.max_runs_per_day ?? ''}
                            onChange={(e) =>
                                form.setData(
                                    'max_runs_per_day',
                                    e.target.value === ''
                                        ? null
                                        : parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.max_runs_per_day} />
                    </Field>
                    <Field label="Max tokens / day">
                        <Input
                            type="number"
                            min="1"
                            value={form.data.max_tokens_per_day ?? ''}
                            onChange={(e) =>
                                form.setData(
                                    'max_tokens_per_day',
                                    e.target.value === ''
                                        ? null
                                        : parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.max_tokens_per_day} />
                    </Field>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        padding: '4px 0',
                    }}
                >
                    <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 13, fontWeight: 600 }}>
                            Enabled
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            Disabled quotas are kept but not enforced.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.enabled}
                        onChange={(v) => form.setData('enabled', v)}
                    />
                </div>
            </div>
        </Modal>
    );
}

function Quotas() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<MaaccQuota | null>(null);
    const quotas = MAACC.quotas;

    const remove = (quota: MaaccQuota) => {
        if (currentTeam && window.confirm('Remove this rate limit?')) {
            router.delete(destroyQuota([currentTeam.slug, quota.id]).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div>
            <Card pad={false}>
                <div
                    style={{
                        padding: '14px 16px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        borderBottom: quotas.length
                            ? '1px solid var(--border)'
                            : 'none',
                    }}
                >
                    <SectionHeader
                        title="Rate limits & quotas"
                        sub="Daily run/token caps enforced at invocation"
                        icon="bolt"
                        style={{ marginBottom: 0 }}
                    />
                    <Btn
                        variant="primary"
                        size="sm"
                        icon="plus"
                        onClick={() => setShowCreate(true)}
                    >
                        New quota
                    </Btn>
                </div>
                {quotas.length === 0 ? (
                    <EmptyState
                        icon="bolt"
                        title="No quotas configured"
                        desc="Add a rate limit to cap daily runs or tokens for an application, project, agent, or model."
                    />
                ) : (
                    <Table
                        columns={[
                            { label: 'Scope' },
                            { label: 'Subject' },
                            { label: 'Environment' },
                            { label: 'Runs / day', align: 'right' },
                            { label: 'Tokens / day', align: 'right' },
                            { label: 'Status' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {quotas.map((q) => (
                            <Tr key={q.id}>
                                <Td strong>{q.scope}</Td>
                                <Td mono>{q.subjectId ?? '—'}</Td>
                                <Td>
                                    <Badge tone="neutral">
                                        {q.environment}
                                    </Badge>
                                </Td>
                                <Td align="right" mono>
                                    {q.maxRunsPerDay ?? '—'}
                                </Td>
                                <Td align="right" mono>
                                    {q.maxTokensPerDay ?? '—'}
                                </Td>
                                <Td>
                                    <Badge
                                        tone={q.enabled ? 'teal' : 'neutral'}
                                        dot
                                    >
                                        {q.enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </Td>
                                <Td align="right">
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 6,
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        <Btn
                                            variant="ghost"
                                            size="icon"
                                            icon="edit"
                                            style={{ height: 28, width: 28 }}
                                            onClick={() => setEditing(q)}
                                        />
                                        <Btn
                                            variant="ghost"
                                            size="icon"
                                            icon="trash"
                                            style={{ height: 28, width: 28 }}
                                            onClick={() => remove(q)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}
            </Card>
            <QuotaFormModal
                open={showCreate}
                onClose={() => setShowCreate(false)}
            />
            {editing && (
                <QuotaFormModal
                    key={editing.id}
                    quota={editing}
                    open
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function RequestApprovalModal({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const form = useForm({
        type: 'agent_publication',
        subject: '',
        environment: 'production',
        change: '',
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.post(requestApproval([currentTeam.slug]).url, {
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
            icon="shield"
            title="Request approval"
            sub="Open a governance approval for a sensitive change."
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Request approval
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Approval type" required>
                    <Select
                        value={form.data.type}
                        onChange={(v) => form.setData('type', v)}
                        options={APPROVAL_TYPE_OPTIONS}
                    />
                    <FieldError error={form.errors.type} />
                </Field>
                <Field
                    label="Subject"
                    required
                    hint="Agent slug, tool slug, model slug, or credential id."
                >
                    <Input
                        value={form.data.subject}
                        onChange={(e) =>
                            form.setData('subject', e.target.value)
                        }
                        placeholder="e.g. procurement-insight"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.subject} />
                </Field>
                <Field label="Environment">
                    <Select
                        value={form.data.environment}
                        onChange={(v) => form.setData('environment', v)}
                        options={ENV_OPTIONS}
                    />
                    <FieldError error={form.errors.environment} />
                </Field>
                <Field label="Change" hint="Optional — what is changing.">
                    <Input
                        value={form.data.change}
                        onChange={(e) => form.setData('change', e.target.value)}
                        placeholder="e.g. promote to Production"
                    />
                    <FieldError error={form.errors.change} />
                </Field>
            </div>
        </Modal>
    );
}

/* ── Page ─────────────────────────────────────────────────── */

export default function Governance() {
    const { scope } = useMaaccNav();
    const MAACC = useMaaccData();
    const isAdmin = scope.isAll;
    const appNames = new Set(scope.apps.map((a) => a.name).concat('Platform'));
    const filt = (items: ApprovalItem[]) =>
        isAdmin ? items : items.filter((it) => appNames.has(it.app));
    const A: ApprovalBuckets = {
        tools: filt(MAACC.approvals.tools),
        agents: filt(MAACC.approvals.agents),
        models: isAdmin ? MAACC.approvals.models : [],
        data: filt(MAACC.approvals.data),
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
              { id: 'quotas', label: 'Rate Limits', icon: 'bolt' },
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
                {tab === 'quotas' && <Quotas />}
                {tab === 'sensitivity' && <DataSensitivity />}
                {tab === 'audit' && (
                    <AuditLog
                        events={MAACC.auditEvents}
                        operational={MAACC.operational}
                    />
                )}
            </div>
        </>
    );
}
