/* ============================================================
   MAACC — Webhooks (Phase 6D)
   Register run-event delivery endpoints, watch their delivery
   health, and replay failed deliveries. Endpoints are signed with
   a one-time secret surfaced by WebhookSecretGate. Every action is
   wired to the tested console write endpoints via Wayfinder.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { replay as replayDelivery } from '@/actions/App/Http/Controllers/Maacc/WebhookDeliveryController';
import {
    destroy as destroyWebhook,
    rotate as rotateWebhook,
    store as storeWebhook,
    update as updateWebhook,
} from '@/actions/App/Http/Controllers/Maacc/WebhookEndpointController';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    EnvBadge,
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
import { ChipMultiSelect, ENV_OPTIONS, FieldError } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';
import type {
    MaaccWebhookDelivery,
    MaaccWebhookEndpoint,
} from '@/types/global';

const EVENT_OPTIONS = [
    { value: '*', label: 'All events' },
    { value: 'run.running', label: 'Run started' },
    { value: 'run.tool_requested', label: 'Tool requested' },
    { value: 'run.completed', label: 'Run completed' },
    { value: 'run.failed', label: 'Run failed' },
    { value: 'run.expired', label: 'Run expired' },
    { value: 'run.cancelled', label: 'Run cancelled' },
];

const DELIVERY_TONE: Record<string, Tone> = {
    delivered: 'teal',
    failed: 'red',
    pending: 'amber',
};

function eventLabels(events: string[]): string {
    if (events.includes('*')) {
        return 'All events';
    }

    return events
        .map((e) => EVENT_OPTIONS.find((o) => o.value === e)?.label ?? e)
        .join(', ');
}

function WebhookFormModal({
    endpoint,
    open,
    onClose,
}: {
    endpoint?: MaaccWebhookEndpoint;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const MAACC = useMaaccData();
    const isEdit = !!endpoint;
    const form = useForm<{
        application_id: string;
        environment: string;
        url: string;
        events: string[];
        description: string;
    }>({
        application_id: MAACC.apps[0]?.uuid ?? MAACC.apps[0]?.id ?? '',
        environment: 'production',
        url: endpoint?.url ?? '',
        events: endpoint?.events ?? ['*'],
        description: endpoint?.description ?? '',
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        if (endpoint) {
            form.put(updateWebhook([currentTeam.slug, endpoint.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeWebhook([currentTeam.slug]).url, {
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
            icon="send"
            title={
                isEdit ? 'Edit webhook endpoint' : 'Register webhook endpoint'
            }
            sub="MAACC posts signed run lifecycle events to this URL."
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
                        {isEdit ? 'Save changes' : 'Register endpoint'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                {!isEdit && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 14,
                        }}
                    >
                        <Field label="Application" required>
                            <Select
                                value={form.data.application_id}
                                onChange={(v) =>
                                    form.setData('application_id', v)
                                }
                                options={MAACC.apps.map((a) => ({
                                    value: a.uuid ?? a.id,
                                    label: a.name,
                                }))}
                            />
                            <FieldError error={form.errors.application_id} />
                        </Field>
                        <Field label="Environment" required>
                            <Select
                                value={form.data.environment}
                                onChange={(v) => form.setData('environment', v)}
                                options={ENV_OPTIONS}
                            />
                            <FieldError error={form.errors.environment} />
                        </Field>
                    </div>
                )}
                <Field
                    label="Endpoint URL"
                    required
                    hint="An HTTPS URL that receives POSTed run events."
                >
                    <Input
                        value={form.data.url}
                        onChange={(e) => form.setData('url', e.target.value)}
                        placeholder="https://app.example.com/webhooks/maacc"
                    />
                    <FieldError error={form.errors.url} />
                </Field>
                <Field
                    label="Events"
                    hint="Pick specific events, or “All events” to receive every transition."
                >
                    <ChipMultiSelect
                        options={EVENT_OPTIONS}
                        selected={form.data.events}
                        onToggle={(value) => {
                            const has = form.data.events.includes(value);
                            const next = has
                                ? form.data.events.filter((e) => e !== value)
                                : [...form.data.events, value];
                            form.setData('events', next.length ? next : ['*']);
                        }}
                    />
                    <FieldError error={form.errors.events} />
                </Field>
                <Field label="Description">
                    <Input
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="Optional — e.g. Ops dashboard listener"
                    />
                    <FieldError error={form.errors.description} />
                </Field>
            </div>
        </Modal>
    );
}

function DeliveryRow({
    delivery,
    teamSlug,
}: {
    delivery: MaaccWebhookDelivery;
    teamSlug: string;
}) {
    const replay = () => {
        router.post(
            replayDelivery([teamSlug, delivery.id]).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <Tr>
            <Td strong>{delivery.eventLabel}</Td>
            <Td>
                <Badge tone={DELIVERY_TONE[delivery.status] ?? 'neutral'} dot>
                    {delivery.statusLabel}
                </Badge>
            </Td>
            <Td align="center">{delivery.attempts}</Td>
            <Td mono>{delivery.responseStatus ?? '—'}</Td>
            <Td>{delivery.error ?? '—'}</Td>
            <Td>{delivery.lastAttemptedAt ?? delivery.createdAt ?? '—'}</Td>
            <Td align="right">
                {delivery.replayable && (
                    <Btn
                        size="sm"
                        variant="soft"
                        icon="refresh"
                        onClick={replay}
                    >
                        Replay
                    </Btn>
                )}
            </Td>
        </Tr>
    );
}

function DeliveriesPanel({
    endpoint,
    teamSlug,
}: {
    endpoint: MaaccWebhookEndpoint;
    teamSlug: string;
}) {
    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title={`Recent deliveries · ${endpoint.url}`}
                sub="The 15 most recent delivery attempts for this endpoint."
                icon="runs"
                style={{ padding: '14px 16px 0' }}
            />
            {endpoint.deliveries.length === 0 ? (
                <EmptyState
                    icon="send"
                    title="No deliveries yet"
                    desc="Deliveries appear here once a run fires a subscribed event."
                />
            ) : (
                <div style={{ padding: 14 }}>
                    <Table
                        columns={[
                            { label: 'Event' },
                            { label: 'Status' },
                            { label: 'Attempts', align: 'center' },
                            { label: 'HTTP' },
                            { label: 'Last error' },
                            { label: 'Last attempt' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {endpoint.deliveries.map((d) => (
                            <DeliveryRow
                                key={d.id}
                                delivery={d}
                                teamSlug={teamSlug}
                            />
                        ))}
                    </Table>
                </div>
            )}
        </Card>
    );
}

export default function Webhooks() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const endpoints = MAACC.webhooks;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaaccWebhookEndpoint | undefined>();
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const selected = endpoints.find((e) => e.id === selectedId) ?? null;
    const active = endpoints.filter((e) => e.status === 'active').length;
    const failing = endpoints.filter((e) => e.lastFailedAt !== null).length;

    const openCreate = () => {
        setEditing(undefined);
        setModalOpen(true);
    };

    const openEdit = (endpoint: MaaccWebhookEndpoint) => {
        setEditing(endpoint);
        setModalOpen(true);
    };

    const toggleStatus = (endpoint: MaaccWebhookEndpoint) => {
        router.put(
            updateWebhook([teamSlug, endpoint.id]).url,
            { status: endpoint.status === 'active' ? 'disabled' : 'active' },
            { preserveScroll: true },
        );
    };

    const rotate = (endpoint: MaaccWebhookEndpoint) => {
        router.post(
            rotateWebhook([teamSlug, endpoint.id]).url,
            {},
            { preserveScroll: true },
        );
    };

    const remove = (endpoint: MaaccWebhookEndpoint) => {
        if (
            window.confirm(`Delete the webhook endpoint for ${endpoint.url}?`)
        ) {
            router.delete(destroyWebhook([teamSlug, endpoint.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    return (
        <>
            <Head title="Webhooks" />
            <div className="route-anim">
                <PageHeader
                    title="Webhooks"
                    sub="Register endpoints to receive signed run lifecycle events — status changes, tool requests, completion, failure, and expiry — so applications can react to runs without holding a request open."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={openCreate}
                            disabled={MAACC.apps.length === 0}
                        >
                            Register endpoint
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
                        label="Endpoints"
                        value={endpoints.length}
                        icon="send"
                    />
                    <Stat
                        label="Active"
                        value={active}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Endpoints with failures"
                        value={failing}
                        icon="alert"
                        tone={failing ? 'red' : 'neutral'}
                    />
                </div>

                {endpoints.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="send"
                            title="No webhook endpoints yet"
                            desc="Register an endpoint to start receiving run lifecycle events. Each endpoint is signed with a one-time secret you verify on delivery."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={openCreate}
                                    disabled={MAACC.apps.length === 0}
                                >
                                    Register endpoint
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Endpoint' },
                            { label: 'Application' },
                            { label: 'Events' },
                            { label: 'Environment' },
                            { label: 'Enabled', align: 'center' },
                            { label: 'Last delivered' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {endpoints.map((e) => (
                            <Tr key={e.id} onClick={() => setSelectedId(e.id)}>
                                <Td strong>
                                    <span style={{ wordBreak: 'break-all' }}>
                                        {e.url}
                                    </span>
                                    {e.lastFailedAt && (
                                        <Badge
                                            tone="red"
                                            soft
                                            style={{ marginLeft: 8 }}
                                        >
                                            Delivery failing
                                        </Badge>
                                    )}
                                </Td>
                                <Td>{e.appName ?? '—'}</Td>
                                <Td>{eventLabels(e.events)}</Td>
                                <Td>
                                    <EnvBadge env={e.environment} />
                                </Td>
                                <Td align="center">
                                    <div
                                        onClick={(ev) => ev.stopPropagation()}
                                        style={{
                                            display: 'inline-flex',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Toggle
                                            on={e.status === 'active'}
                                            onChange={() => toggleStatus(e)}
                                            size="sm"
                                        />
                                    </div>
                                </Td>
                                <Td>{e.lastDeliveredAt ?? '—'}</Td>
                                <Td align="right">
                                    <div
                                        onClick={(ev) => ev.stopPropagation()}
                                        style={{
                                            display: 'inline-flex',
                                            gap: 6,
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        <IconBtn
                                            icon="key"
                                            title="Rotate secret"
                                            onClick={() => rotate(e)}
                                        />
                                        <IconBtn
                                            icon="edit"
                                            title="Edit"
                                            onClick={() => openEdit(e)}
                                        />
                                        <IconBtn
                                            icon="trash"
                                            title="Delete"
                                            danger
                                            onClick={() => remove(e)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                {selected && (
                    <DeliveriesPanel endpoint={selected} teamSlug={teamSlug} />
                )}

                <WebhookFormModal
                    endpoint={editing}
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
