/* ============================================================
   MAAC — Read-only Data Sources (Phase 8A)
   Register governed read-only data sources (replicas / reporting
   views) a `db` tool may query. MAAC stores only the approved
   connection NAME — never a connection string or credential —
   and resolves any injected credential from the secrets vault.
   A sensitive source is gated behind an access approval before
   the runtime may query it. Every action is wired to the tested
   console write endpoints via Wayfinder.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroySource,
    refresh as refreshSource,
    store as storeSource,
    update as updateSource,
} from '@/actions/App/Http/Controllers/Maac/DataSourceController';
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
    DB_CONNECTION_TYPE_OPTIONS,
    ENV_OPTIONS,
    FieldError,
    SENSITIVITY_OPTIONS,
} from '@/maac/forms';
import { Icon } from '@/maac/icons';
import { useMaacData } from '@/maac/use-data';
import type { MaacDataSource } from '@/types/global';

const NO_APP = 'none';
const NO_SECRET = 'none';

function DataSourceFormModal({
    source,
    open,
    onClose,
    connections,
}: {
    source?: MaacDataSource;
    open: boolean;
    onClose: () => void;
    connections: string[];
}) {
    const { currentTeam } = usePage().props;
    const MAAC = useMaacData();
    const isEdit = !!source;
    const dbSecrets = MAAC.vaultSecrets.filter((s) => s.kind === 'database');

    const form = useForm<{
        name: string;
        application_id: string;
        description: string;
        connection_type: string;
        connection: string;
        vault_secret_id: string;
        sensitivity: string;
        requires_approval: boolean;
        environments: string[];
        allowed_relations: string;
        max_rows: number;
        statement_timeout_ms: number;
        max_result_kb: number;
        staleness_threshold_minutes: string;
    }>({
        name: source?.name ?? '',
        application_id: NO_APP,
        description: source?.description ?? '',
        connection_type: source?.connectionType ?? 'read_replica',
        connection: source ? '' : (connections[0] ?? ''),
        vault_secret_id: NO_SECRET,
        sensitivity: (source?.sensitivity ?? 'Internal').toLowerCase(),
        requires_approval: source?.requiresApproval ?? false,
        environments: source
            ? source.environments.map((e) => e.toLowerCase())
            : ['production'],
        allowed_relations: source ? source.allowedRelations.join(', ') : '',
        max_rows: source?.maxRows ?? 100,
        statement_timeout_ms: source?.statementTimeoutMs ?? 5000,
        max_result_kb: source?.maxResultKb ?? 256,
        staleness_threshold_minutes: source?.stalenessThresholdMinutes
            ? String(source.stalenessThresholdMinutes)
            : '',
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
            vault_secret_id:
                data.vault_secret_id === NO_SECRET
                    ? null
                    : data.vault_secret_id,
            allowed_relations: data.allowed_relations
                .split(',')
                .map((r) => r.trim())
                .filter(Boolean),
            staleness_threshold_minutes:
                data.staleness_threshold_minutes === ''
                    ? null
                    : parseInt(data.staleness_threshold_minutes, 10),
        }));

        if (source) {
            form.put(updateSource([currentTeam.slug, source.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeSource([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const connectionOptions = connections.map((c) => ({ value: c, label: c }));
    const secretOptions = [
        { value: NO_SECRET, label: 'Connection-managed (no vault secret)' },
        ...dbSecrets.map((s) => ({ value: s.uuid, label: s.name })),
    ];
    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };
    const third = {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr 1fr',
        gap: 14,
    };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="database"
            title={isEdit ? 'Edit data source' : 'Register data source'}
            sub="A governed read-only surface (replica / reporting view) a db tool may query. MAAC stores only the approved connection name — never a connection string or credential."
            width={640}
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
                        {isEdit ? 'Save changes' : 'Register data source'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div
                    style={{
                        fontSize: 12,
                        lineHeight: 1.5,
                        color: 'var(--amber-700, var(--text-2))',
                        background: 'var(--amber-soft, var(--surface-3))',
                        border: '1px solid var(--border-2)',
                        borderRadius: 'var(--r-sm)',
                        padding: '8px 12px',
                    }}
                >
                    <Icon
                        name="shield"
                        size={12}
                        style={{ verticalAlign: '-1px', marginRight: 6 }}
                    />
                    Governed reporting access only — point this at an approved
                    read-only replica, materialized view, or reporting schema.
                    This is <strong>not</strong> for application-owned
                    transactional data; use a client-side tool for that.
                </div>
                <Field label="Data source name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Port Calls Reporting Replica"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <div style={half}>
                    <Field label="Surface type" required>
                        <Select
                            value={form.data.connection_type}
                            onChange={(v) => form.setData('connection_type', v)}
                            options={DB_CONNECTION_TYPE_OPTIONS}
                        />
                        <FieldError error={form.errors.connection_type} />
                    </Field>
                    <Field
                        label="Data sensitivity"
                        required
                        hint="Confidential+ is gated behind access approval."
                    >
                        <Select
                            value={form.data.sensitivity}
                            onChange={(v) => form.setData('sensitivity', v)}
                            options={SENSITIVITY_OPTIONS}
                        />
                        <FieldError error={form.errors.sensitivity} />
                    </Field>
                </div>
                <Field
                    label={
                        isEdit
                            ? 'Approved connection (leave to keep)'
                            : 'Approved read-only connection'
                    }
                    required={!isEdit}
                    hint="An ops-provisioned read-only connection from the platform allowlist."
                >
                    {connectionOptions.length === 0 ? (
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            No approved read-only connections are configured.
                            Ask an operator to add one to the platform
                            allowlist.
                        </div>
                    ) : (
                        <Select
                            value={form.data.connection}
                            onChange={(v) => form.setData('connection', v)}
                            options={
                                isEdit
                                    ? [
                                          { value: '', label: '— unchanged —' },
                                          ...connectionOptions,
                                      ]
                                    : connectionOptions
                            }
                        />
                    )}
                    <FieldError error={form.errors.connection} />
                </Field>
                <Field
                    label="Credential (vault)"
                    hint="Optionally resolve the connection credential from the secrets vault."
                >
                    <Select
                        value={form.data.vault_secret_id}
                        onChange={(v) => form.setData('vault_secret_id', v)}
                        options={secretOptions}
                    />
                    <FieldError error={form.errors.vault_secret_id} />
                </Field>
                <Field
                    label="Approved query surface"
                    required
                    hint="Comma-separated allowlist of views/tables a query may reference."
                >
                    <Input
                        value={form.data.allowed_relations}
                        onChange={(e) =>
                            form.setData('allowed_relations', e.target.value)
                        }
                        placeholder="reporting_port_calls, reporting_vessels"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.allowed_relations} />
                </Field>
                <Field
                    label="Environments"
                    required
                    hint="Where this source may be queried by the runtime."
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
                <div style={third}>
                    <Field label="Max rows" required>
                        <Input
                            type="number"
                            min="1"
                            value={form.data.max_rows}
                            onChange={(e) =>
                                form.setData(
                                    'max_rows',
                                    parseInt(e.target.value, 10) || 1,
                                )
                            }
                        />
                        <FieldError error={form.errors.max_rows} />
                    </Field>
                    <Field label="Max result (KB)" required>
                        <Input
                            type="number"
                            min="1"
                            value={form.data.max_result_kb}
                            onChange={(e) =>
                                form.setData(
                                    'max_result_kb',
                                    parseInt(e.target.value, 10) || 1,
                                )
                            }
                        />
                        <FieldError error={form.errors.max_result_kb} />
                    </Field>
                    <Field label="Stmt timeout (ms)" required>
                        <Input
                            type="number"
                            min="100"
                            value={form.data.statement_timeout_ms}
                            onChange={(e) =>
                                form.setData(
                                    'statement_timeout_ms',
                                    parseInt(e.target.value, 10) || 100,
                                )
                            }
                        />
                        <FieldError error={form.errors.statement_timeout_ms} />
                    </Field>
                </div>
                <Field
                    label="Staleness threshold (minutes)"
                    hint="Optional. A query fails if the source's data is older than this."
                >
                    <Input
                        type="number"
                        min="1"
                        value={form.data.staleness_threshold_minutes}
                        onChange={(e) =>
                            form.setData(
                                'staleness_threshold_minutes',
                                e.target.value,
                            )
                        }
                        placeholder="e.g. 1440 (24h)"
                    />
                    <FieldError
                        error={form.errors.staleness_threshold_minutes}
                    />
                </Field>
                <Field label="Description / business purpose">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What governed reporting data does this source expose?"
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
                            Gate this source behind a governance access approval
                            before queries.
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

function DetailPanel({ source }: { source: MaacDataSource }) {
    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title={`Query surface · ${source.name}`}
                sub={
                    source.dataRefreshed
                        ? `Data refreshed ${source.dataRefreshed}.`
                        : 'Freshness not tracked for this source.'
                }
                icon="database"
                style={{ padding: '14px 16px 0' }}
            />
            <div style={{ padding: 14 }}>
                <Table columns={[{ label: 'Approved relation' }]}>
                    {source.allowedRelations.length === 0 ? (
                        <Tr>
                            <Td>—</Td>
                        </Tr>
                    ) : (
                        source.allowedRelations.map((relation) => (
                            <Tr key={relation}>
                                <Td mono strong>
                                    {relation}
                                </Td>
                            </Tr>
                        ))
                    )}
                </Table>
                <div
                    style={{
                        marginTop: 12,
                        fontSize: 12,
                        color: 'var(--text-3)',
                        display: 'flex',
                        gap: 16,
                        flexWrap: 'wrap',
                    }}
                >
                    <span>Surface: {source.connectionTypeLabel}</span>
                    <span>Driver: {source.driver ?? '—'}</span>
                    <span>Max rows: {source.maxRows}</span>
                    <span>Max result: {source.maxResultKb} KB</span>
                    <span>
                        Credential:{' '}
                        {source.credentialManaged
                            ? 'Vault-managed'
                            : 'Connection-managed'}
                    </span>
                </div>
            </div>
        </Card>
    );
}

export default function DataSources() {
    const MAAC = useMaacData();
    const { currentTeam, connections } = usePage<{
        connections: string[];
    }>().props;
    const teamSlug = currentTeam?.slug ?? '';
    const sources = MAAC.dataSources;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaacDataSource | undefined>();
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const selected = sources.find((s) => s.id === selectedId) ?? null;
    const active = sources.filter((s) => s.status === 'active').length;
    const gated = sources.filter((s) => s.status === 'draft').length;

    const openCreate = () => {
        setEditing(undefined);
        setModalOpen(true);
    };

    const openEdit = (source: MaacDataSource) => {
        setEditing(source);
        setModalOpen(true);
    };

    const toggleStatus = (source: MaacDataSource) => {
        router.put(
            updateSource([teamSlug, source.id]).url,
            { status: source.status === 'active' ? 'disabled' : 'active' },
            { preserveScroll: true },
        );
    };

    const markRefreshed = (source: MaacDataSource) => {
        router.post(
            refreshSource([teamSlug, source.id]).url,
            {},
            { preserveScroll: true },
        );
    };

    const remove = (source: MaacDataSource) => {
        if (window.confirm(`Delete the data source ${source.name}?`)) {
            router.delete(destroySource([teamSlug, source.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    return (
        <>
            <Head title="Data Sources" />
            <div className="route-anim">
                <PageHeader
                    title="Read-only Data Sources"
                    sub="Register governed read-only data sources — replicas, materialized views, or reporting schemas — that a db tool may query. MAAC executes the query server-side under strict statement-type, query-surface, row, and result-size controls, stores only the approved connection name (never a connection string or credential), and resolves any credential from the secrets vault."
                    actions={
                        <Btn variant="primary" icon="plus" onClick={openCreate}>
                            Register data source
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
                        label="Data sources"
                        value={sources.length}
                        icon="database"
                    />
                    <Stat
                        label="Active"
                        value={active}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Pending approval"
                        value={gated}
                        icon="shield"
                        tone="amber"
                    />
                </div>

                {sources.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="database"
                            title="No data sources yet"
                            desc="Register an approved read-only data source so a db tool can run governed reporting queries against it. MAAC never touches application-owned transactional data this way."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={openCreate}
                                >
                                    Register data source
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Data source' },
                            { label: 'Surface' },
                            { label: 'Sensitivity' },
                            { label: 'Environments' },
                            { label: 'Tools', align: 'center' },
                            { label: 'Enabled', align: 'center' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {sources.map((s) => (
                            <Tr key={s.id} onClick={() => setSelectedId(s.id)}>
                                <Td strong>
                                    {s.name}
                                    {s.status === 'draft' && (
                                        <Badge
                                            tone="amber"
                                            soft
                                            style={{ marginLeft: 8 }}
                                        >
                                            Pending approval
                                        </Badge>
                                    )}
                                    {s.credentialManaged && (
                                        <Badge
                                            tone="teal"
                                            soft
                                            style={{ marginLeft: 8 }}
                                        >
                                            Vault
                                        </Badge>
                                    )}
                                </Td>
                                <Td>{s.connectionTypeLabel}</Td>
                                <Td>
                                    <Badge tone="neutral" soft>
                                        {s.sensitivity}
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
                                        {s.environments.map((env) => (
                                            <EnvBadge key={env} env={env} />
                                        ))}
                                    </div>
                                </Td>
                                <Td align="center">{s.toolCount ?? 0}</Td>
                                <Td align="center">
                                    <div
                                        onClick={(ev) => ev.stopPropagation()}
                                        style={{
                                            display: 'inline-flex',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Toggle
                                            on={s.status === 'active'}
                                            onChange={() => toggleStatus(s)}
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
                                            title="Mark data refreshed"
                                            onClick={() => markRefreshed(s)}
                                        />
                                        <IconBtn
                                            icon="edit"
                                            title="Edit"
                                            onClick={() => openEdit(s)}
                                        />
                                        <IconBtn
                                            icon="trash"
                                            title="Delete"
                                            danger
                                            onClick={() => remove(s)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                {selected && <DetailPanel source={selected} />}

                <DataSourceFormModal
                    source={editing}
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                    connections={connections ?? []}
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
