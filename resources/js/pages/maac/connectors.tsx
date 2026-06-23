/* ============================================================
   MAAC — MCP Connectors (Phase 6E)
   Register external MCP servers MAAC connects to as a client,
   discover their capabilities, and map them to connector-backed
   tool contracts. Auth credentials are write-only (stored
   encrypted, never re-displayed). Every action is wired to the
   tested console write endpoints via Wayfinder.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyConnector,
    discover as discoverConnector,
    store as storeConnector,
    update as updateConnector,
} from '@/actions/App/Http/Controllers/Maac/McpConnectorController';
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
    Textarea,
    Toggle,
    Tr,
} from '@/components/maac/ui';
import type { Tone } from '@/components/maac/ui';
import {
    ChipMultiSelect,
    ENV_OPTIONS,
    FieldError,
    REMOTE_AUTH_OPTIONS,
    SENSITIVITY_OPTIONS,
} from '@/maac/forms';
import { Icon } from '@/maac/icons';
import { useMaacData } from '@/maac/use-data';
import type { MaacConnector } from '@/types/global';

const NO_APP = 'none';

function ConnectorFormModal({
    connector,
    open,
    onClose,
}: {
    connector?: MaacConnector;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const isEdit = !!connector;
    const form = useForm<{
        name: string;
        application_id: string;
        description: string;
        server_url: string;
        auth_type: string;
        auth_header: string;
        auth_credential: string;
        sensitivity: string;
        requires_approval: boolean;
        environments: string[];
        timeout_seconds: number;
    }>({
        name: connector?.name ?? '',
        application_id: NO_APP,
        description: connector?.description ?? '',
        server_url: connector?.serverUrl ?? '',
        auth_type: connector?.authType ?? 'none',
        auth_header: connector?.authHeader ?? '',
        auth_credential: '',
        sensitivity: (connector?.sensitivity ?? 'Internal').toLowerCase(),
        requires_approval: connector?.requiresApproval ?? false,
        environments: connector
            ? connector.environments.map((e) => e.toLowerCase())
            : ['production'],
        timeout_seconds: 20,
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
            application_id:
                data.application_id === NO_APP ? null : data.application_id,
        }));

        if (connector) {
            form.put(updateConnector([currentTeam.slug, connector.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeConnector([currentTeam.slug]).url, {
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
            icon="layers"
            title={isEdit ? 'Edit MCP connector' : 'Register MCP connector'}
            sub="MAAC connects to this external MCP server as a client to run connector-backed tools."
            width={600}
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
                        {isEdit ? 'Save changes' : 'Register connector'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Connector name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Logistics MCP"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <Field
                    label="Server URL"
                    required
                    hint="The MCP server's Streamable HTTP endpoint."
                >
                    <Input
                        value={form.data.server_url}
                        onChange={(e) =>
                            form.setData('server_url', e.target.value)
                        }
                        placeholder="https://mcp.example.com/mcp"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.server_url} />
                </Field>
                <div style={half}>
                    <Field label="Authentication" required>
                        <Select
                            value={form.data.auth_type}
                            onChange={(v) => form.setData('auth_type', v)}
                            options={REMOTE_AUTH_OPTIONS}
                        />
                        <FieldError error={form.errors.auth_type} />
                    </Field>
                    <Field label="Data sensitivity" required>
                        <Select
                            value={form.data.sensitivity}
                            onChange={(v) => form.setData('sensitivity', v)}
                            options={SENSITIVITY_OPTIONS}
                        />
                        <FieldError error={form.errors.sensitivity} />
                    </Field>
                </div>
                {form.data.auth_type === 'header' && (
                    <Field label="Header name" required>
                        <Input
                            value={form.data.auth_header}
                            onChange={(e) =>
                                form.setData('auth_header', e.target.value)
                            }
                            placeholder="X-Api-Key"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.auth_header} />
                    </Field>
                )}
                {form.data.auth_type !== 'none' && (
                    <Field
                        label={
                            isEdit
                                ? 'Credential (leave blank to keep)'
                                : 'Credential'
                        }
                        hint="Stored encrypted and never displayed again."
                    >
                        <Input
                            type="password"
                            value={form.data.auth_credential}
                            onChange={(e) =>
                                form.setData('auth_credential', e.target.value)
                            }
                            placeholder={
                                isEdit && connector?.authConfigured
                                    ? '•••••••• (unchanged)'
                                    : 'token or key value'
                            }
                        />
                        <FieldError error={form.errors.auth_credential} />
                    </Field>
                )}
                <Field
                    label="Environments"
                    required
                    hint="Where this connector may be invoked by the runtime."
                >
                    <ChipMultiSelect
                        options={ENV_OPTIONS}
                        selected={form.data.environments}
                        onToggle={(value) => {
                            const has = form.data.environments.includes(value);
                            form.setData(
                                'environments',
                                has
                                    ? form.data.environments.filter(
                                          (e) => e !== value,
                                      )
                                    : [...form.data.environments, value],
                            );
                        }}
                    />
                    <FieldError error={form.errors.environments} />
                </Field>
                <Field label="Description">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What capabilities does this connector expose?"
                    />
                    <FieldError error={form.errors.description} />
                </Field>
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
                            Requires approval
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            Gate connector-backed tools behind a governance
                            approval before production use.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.requires_approval}
                        onChange={(v) => form.setData('requires_approval', v)}
                    />
                </div>
            </div>
        </Modal>
    );
}

function CapabilitiesPanel({ connector }: { connector: MaacConnector }) {
    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title={`Discovered capabilities · ${connector.name}`}
                sub={
                    connector.lastDiscovered
                        ? `Last discovered ${connector.lastDiscovered}.`
                        : 'Run discovery to list this connector’s remote tools.'
                }
                icon="layers"
                style={{ padding: '14px 16px 0' }}
            />
            {connector.capabilities.length === 0 ? (
                <EmptyState
                    icon="layers"
                    title="No capabilities discovered yet"
                    desc="Run discovery to fetch the remote tools this MCP server exposes."
                />
            ) : (
                <div style={{ padding: 14 }}>
                    <Table
                        columns={[
                            { label: 'Remote tool' },
                            { label: 'Description' },
                        ]}
                    >
                        {connector.capabilities.map((cap) => (
                            <Tr key={cap.name}>
                                <Td mono strong>
                                    {cap.name}
                                </Td>
                                <Td>{cap.description ?? cap.title ?? '—'}</Td>
                            </Tr>
                        ))}
                    </Table>
                </div>
            )}
        </Card>
    );
}

export default function Connectors() {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const connectors = MAAC.connectors;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaacConnector | undefined>();
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const selected = connectors.find((c) => c.id === selectedId) ?? null;
    const active = connectors.filter((c) => c.status === 'active').length;
    const discovered = connectors.filter(
        (c) => c.capabilities.length > 0,
    ).length;

    const openCreate = () => {
        setEditing(undefined);
        setModalOpen(true);
    };

    const openEdit = (connector: MaacConnector) => {
        setEditing(connector);
        setModalOpen(true);
    };

    const toggleStatus = (connector: MaacConnector) => {
        router.put(
            updateConnector([teamSlug, connector.id]).url,
            { status: connector.status === 'active' ? 'disabled' : 'active' },
            { preserveScroll: true },
        );
    };

    const discover = (connector: MaacConnector) => {
        router.post(
            discoverConnector([teamSlug, connector.id]).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSelectedId(connector.id),
            },
        );
    };

    const remove = (connector: MaacConnector) => {
        if (window.confirm(`Delete the connector ${connector.name}?`)) {
            router.delete(destroyConnector([teamSlug, connector.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    return (
        <>
            <Head title="MCP Connectors" />
            <div className="route-anim">
                <PageHeader
                    title="MCP Connectors"
                    sub="Register external MCP servers MAAC connects to as a client. Discover their tools, then map them to connector-backed tool contracts — MAAC executes them server-side with the same schema, governance, and audit standards as every other tool."
                    actions={
                        <Btn variant="primary" icon="plus" onClick={openCreate}>
                            Register connector
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
                        label="Connectors"
                        value={connectors.length}
                        icon="layers"
                    />
                    <Stat
                        label="Active"
                        value={active}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="With capabilities"
                        value={discovered}
                        icon="sparkles"
                        tone="purple"
                    />
                </div>

                {connectors.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="layers"
                            title="No MCP connectors yet"
                            desc="Register an external MCP server to expose its tools to your agents. MAAC discovers each server’s capabilities and runs the mapped tools server-side."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={openCreate}
                                >
                                    Register connector
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Connector' },
                            { label: 'Server URL' },
                            { label: 'Auth' },
                            { label: 'Environments' },
                            { label: 'Tools', align: 'center' },
                            { label: 'Enabled', align: 'center' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {connectors.map((c) => (
                            <Tr key={c.id} onClick={() => setSelectedId(c.id)}>
                                <Td strong>
                                    {c.name}
                                    {c.requiresApproval && (
                                        <Badge
                                            tone="amber"
                                            soft
                                            style={{ marginLeft: 8 }}
                                        >
                                            Approval
                                        </Badge>
                                    )}
                                </Td>
                                <Td>
                                    <span
                                        style={{
                                            fontFamily: 'var(--mono)',
                                            fontSize: 12,
                                            wordBreak: 'break-all',
                                        }}
                                    >
                                        {c.serverUrl}
                                    </span>
                                </Td>
                                <Td>
                                    <Badge
                                        tone={
                                            c.authConfigured
                                                ? 'teal'
                                                : 'neutral'
                                        }
                                        soft
                                    >
                                        {c.authType}
                                    </Badge>
                                </Td>
                                <Td>
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 4,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        {c.environments.map((env) => (
                                            <EnvBadge key={env} env={env} />
                                        ))}
                                    </div>
                                </Td>
                                <Td align="center">{c.toolCount ?? 0}</Td>
                                <Td align="center">
                                    <div
                                        onClick={(ev) => ev.stopPropagation()}
                                        style={{
                                            display: 'inline-flex',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Toggle
                                            on={c.status === 'active'}
                                            onChange={() => toggleStatus(c)}
                                            size="sm"
                                        />
                                    </div>
                                </Td>
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
                                            icon="refresh"
                                            title="Discover capabilities"
                                            onClick={() => discover(c)}
                                        />
                                        <IconBtn
                                            icon="edit"
                                            title="Edit"
                                            onClick={() => openEdit(c)}
                                        />
                                        <IconBtn
                                            icon="trash"
                                            title="Delete"
                                            danger
                                            onClick={() => remove(c)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                {selected && <CapabilitiesPanel connector={selected} />}

                <ConnectorFormModal
                    connector={editing}
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
            className="maac-iconbtn"
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
