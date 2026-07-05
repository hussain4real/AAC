/* ============================================================
   MAACC — Advanced Model Routing (Phase 6G)
   Define how an agent selects its model: a strategy (cost /
   latency / balanced), an ordered candidate chain (primary +
   fallbacks), and cost/latency ceilings. The runtime filters
   candidates by environment, sensitivity clearance, and recent
   provider health, and fails over along the chain on a model error.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyPolicy,
    store as storePolicy,
    update as updatePolicy,
} from '@/actions/App/Http/Controllers/Maacc/ModelRoutingPolicyController';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    Field,
    Input,
    Modal,
    PageHeader,
    SectionHeader,
    Select,
    Table,
    Td,
    Toggle,
    Tr,
} from '@/components/maacc/ui';
import type { Tone } from '@/components/maacc/ui';
import { ChipMultiSelect, FieldError } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';
import type { MaaccRoutingPolicy } from '@/types/global';

const STRATEGY_OPTIONS = [
    { value: 'balanced', label: 'Balanced' },
    { value: 'cost', label: 'Cost Optimized' },
    { value: 'latency', label: 'Latency Optimized' },
];

function PolicyFormModal({
    policy,
    open,
    onClose,
}: {
    policy?: MaaccRoutingPolicy;
    open: boolean;
    onClose: () => void;
}) {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const isEdit = !!policy;
    const modelOptions = MAACC.llms.map((l) => ({
        value: l.uuid ?? '',
        label: l.name,
    }));

    const form = useForm<{
        name: string;
        agent_id: string;
        strategy: string;
        primary_provider_id: string;
        fallback_provider_ids: string[];
        max_cost_per_1k: string;
        max_latency_ms: string;
        enabled: boolean;
    }>({
        name: policy?.name ?? '',
        agent_id: policy?.agentId ?? 'none',
        strategy: policy?.strategy ?? 'balanced',
        primary_provider_id: policy?.primaryProviderId ?? 'none',
        fallback_provider_ids: policy?.fallbackProviderIds ?? [],
        max_cost_per_1k:
            policy?.maxCostPer1k != null ? String(policy.maxCostPer1k) : '',
        max_latency_ms:
            policy?.maxLatencyMs != null ? String(policy.maxLatencyMs) : '',
        enabled: policy?.enabled ?? true,
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.transform((data) => ({
            ...data,
            primary_provider_id:
                data.primary_provider_id && data.primary_provider_id !== 'none'
                    ? data.primary_provider_id
                    : null,
            max_cost_per_1k:
                data.max_cost_per_1k === ''
                    ? null
                    : Number(data.max_cost_per_1k),
            max_latency_ms:
                data.max_latency_ms === '' ? null : Number(data.max_latency_ms),
        }));

        if (policy) {
            form.put(updatePolicy([currentTeam.slug, policy.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storePolicy([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="flow"
            title={isEdit ? 'Edit routing policy' : 'Create routing policy'}
            sub="The runtime applies this policy to select the agent's model and to fail over when a model call errors."
            width={600}
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={
                            form.processing ||
                            (!isEdit && form.data.agent_id === 'none')
                        }
                        onClick={submit}
                    >
                        {isEdit ? 'Save changes' : 'Create policy'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Policy name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Tiered cost routing"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                {!isEdit && (
                    <Field label="Agent" required hint="One policy per agent.">
                        <Select
                            value={form.data.agent_id}
                            onChange={(v) => form.setData('agent_id', v)}
                            options={[
                                { value: 'none', label: 'Select an agent…' },
                                ...MAACC.agents.map((a) => ({
                                    value: a.uuid ?? '',
                                    label: a.name,
                                })),
                            ]}
                        />
                        <FieldError error={form.errors.agent_id} />
                    </Field>
                )}
                <div style={half}>
                    <Field label="Strategy" required>
                        <Select
                            value={form.data.strategy}
                            onChange={(v) => form.setData('strategy', v)}
                            options={STRATEGY_OPTIONS}
                        />
                        <FieldError error={form.errors.strategy} />
                    </Field>
                    <Field
                        label="Primary model"
                        hint="Defaults to the agent's model."
                    >
                        <Select
                            value={form.data.primary_provider_id}
                            onChange={(v) =>
                                form.setData('primary_provider_id', v)
                            }
                            options={[
                                { value: 'none', label: 'Agent default' },
                                ...modelOptions,
                            ]}
                        />
                        <FieldError error={form.errors.primary_provider_id} />
                    </Field>
                </div>
                <Field
                    label="Fallback chain"
                    hint="Tried in order when the primary is unavailable or errors."
                >
                    <ChipMultiSelect
                        options={modelOptions}
                        selected={form.data.fallback_provider_ids}
                        onToggle={(value) => {
                            const has =
                                form.data.fallback_provider_ids.includes(value);
                            form.setData(
                                'fallback_provider_ids',
                                has
                                    ? form.data.fallback_provider_ids.filter(
                                          (id) => id !== value,
                                      )
                                    : [
                                          ...form.data.fallback_provider_ids,
                                          value,
                                      ],
                            );
                        }}
                        empty="No approved models available."
                    />
                    <FieldError error={form.errors.fallback_provider_ids} />
                </Field>
                <div style={half}>
                    <Field
                        label="Max cost / 1K"
                        hint="Exclude costlier models."
                    >
                        <Input
                            type="number"
                            value={form.data.max_cost_per_1k}
                            onChange={(e) =>
                                form.setData('max_cost_per_1k', e.target.value)
                            }
                            placeholder="e.g. 8.00"
                        />
                        <FieldError error={form.errors.max_cost_per_1k} />
                    </Field>
                    <Field
                        label="Max latency (ms)"
                        hint="Deprioritize slower models."
                    >
                        <Input
                            type="number"
                            value={form.data.max_latency_ms}
                            onChange={(e) =>
                                form.setData('max_latency_ms', e.target.value)
                            }
                            placeholder="e.g. 4000"
                        />
                        <FieldError error={form.errors.max_latency_ms} />
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
                            When off, the agent uses its configured model
                            directly.
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

function ProviderHealthPanel() {
    const MAACC = useMaaccData();

    if (MAACC.providerHealth.length === 0) {
        return null;
    }

    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title="Provider health"
                sub="Recent model-attributable failure rate and latency — the router deprioritizes degraded providers."
                icon="cpu"
                style={{ padding: '14px 16px 0' }}
            />
            <div style={{ padding: 14 }}>
                <Table
                    columns={[
                        { label: 'Model' },
                        { label: 'Health', align: 'center' },
                        { label: 'Failure rate', align: 'center' },
                        { label: 'Avg latency', align: 'center' },
                        { label: 'Sample', align: 'center' },
                    ]}
                >
                    {MAACC.providerHealth.map((h) => (
                        <Tr key={h.id}>
                            <Td strong>{h.name}</Td>
                            <Td align="center">
                                <Badge tone={h.healthy ? 'teal' : 'red'} dot>
                                    {h.healthy ? 'Healthy' : 'Degraded'}
                                </Badge>
                            </Td>
                            <Td align="center">
                                {(h.failureRate * 100).toFixed(0)}%
                            </Td>
                            <Td align="center">
                                {h.avgLatencyMs != null
                                    ? `${h.avgLatencyMs} ms`
                                    : '—'}
                            </Td>
                            <Td align="center">{h.sampleSize}</Td>
                        </Tr>
                    ))}
                </Table>
            </div>
        </Card>
    );
}

export default function Routing() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const policies = MAACC.routingPolicies;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaaccRoutingPolicy | undefined>();

    const enabled = policies.filter((p) => p.enabled).length;
    const degraded = MAACC.providerHealth.filter((h) => !h.healthy).length;

    const remove = (policy: MaaccRoutingPolicy) => {
        if (window.confirm(`Delete the routing policy ${policy.name}?`)) {
            router.delete(destroyPolicy([teamSlug, policy.id]).url, {
                preserveScroll: true,
            });
        }
    };

    const toggle = (policy: MaaccRoutingPolicy) => {
        router.put(
            updatePolicy([teamSlug, policy.id]).url,
            { enabled: !policy.enabled },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Model Routing" />
            <div className="route-anim">
                <PageHeader
                    title="Advanced Model Routing"
                    sub="Route each agent's model by strategy, an ordered candidate chain, and cost/latency ceilings. The runtime filters candidates by environment availability, sensitivity clearance, and recent provider health, and fails over along the chain when a model call errors."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => {
                                setEditing(undefined);
                                setModalOpen(true);
                            }}
                        >
                            New policy
                        </Btn>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <Stat
                        label="Policies"
                        value={policies.length}
                        icon="flow"
                    />
                    <Stat
                        label="Enabled"
                        value={enabled}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Degraded models"
                        value={degraded}
                        icon="alert"
                        tone={degraded > 0 ? 'red' : 'neutral'}
                    />
                </div>

                {policies.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="flow"
                            title="No routing policies yet"
                            desc="Create a policy to route an agent across multiple models with cost/latency constraints and automatic fail-over."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={() => {
                                        setEditing(undefined);
                                        setModalOpen(true);
                                    }}
                                >
                                    New policy
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Policy' },
                            { label: 'Agent' },
                            { label: 'Strategy' },
                            { label: 'Fallbacks', align: 'center' },
                            { label: 'Ceilings' },
                            { label: 'Enabled', align: 'center' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {policies.map((p) => (
                            <Tr key={p.id}>
                                <Td strong>{p.name}</Td>
                                <Td>{p.agentName ?? '—'}</Td>
                                <Td>
                                    <Badge tone="purple" soft>
                                        {p.strategyLabel}
                                    </Badge>
                                </Td>
                                <Td align="center">
                                    {p.fallbackProviderIds.length}
                                </Td>
                                <Td>
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {p.maxCostPer1k != null
                                            ? `≤ $${p.maxCostPer1k}/1K`
                                            : '—'}
                                        {p.maxLatencyMs != null
                                            ? ` · ≤ ${p.maxLatencyMs}ms`
                                            : ''}
                                    </span>
                                </Td>
                                <Td align="center">
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Toggle
                                            on={p.enabled}
                                            onChange={() => toggle(p)}
                                            size="sm"
                                        />
                                    </div>
                                </Td>
                                <Td align="right">
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            gap: 6,
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        <IconBtn
                                            icon="edit"
                                            title="Edit"
                                            onClick={() => {
                                                setEditing(p);
                                                setModalOpen(true);
                                            }}
                                        />
                                        <IconBtn
                                            icon="trash"
                                            title="Delete"
                                            danger
                                            onClick={() => remove(p)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                <ProviderHealthPanel />

                <PolicyFormModal
                    policy={editing}
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                />
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    icon,
    tone = 'purple',
}: {
    label: string;
    value: number;
    icon: string;
    tone?: Tone;
}) {
    return (
        <Card>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <Badge tone={tone} soft style={{ height: 30, width: 30 }}>
                    <Icon name={icon} size={15} />
                </Badge>
                <div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{value}</div>
                    <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {label}
                    </div>
                </div>
            </div>
        </Card>
    );
}

function IconBtn({
    icon,
    title,
    onClick,
    danger = false,
}: {
    icon: string;
    title: string;
    onClick: () => void;
    danger?: boolean;
}) {
    return (
        <button
            title={title}
            onClick={onClick}
            className="maacc-iconbtn"
            style={{
                border: '1px solid var(--border-2)',
                background: 'var(--surface)',
                cursor: 'pointer',
                color: danger ? 'var(--red-600)' : 'var(--text-2)',
                padding: 6,
                display: 'flex',
                borderRadius: 'var(--r-xs)',
            }}
        >
            <Icon name={icon} size={14} />
        </button>
    );
}
