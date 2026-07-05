/* ============================================================
   MAACC — Enterprise Identity / SSO (Phase 6G)
   Register OAuth 2.0 / OIDC providers that web users authenticate
   through. The connection's claim mapping and group→role rules map
   an external identity onto MAACC team/project roles. The client
   secret is write-only (stored encrypted, never re-displayed).
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyConnection,
    store as storeConnection,
    update as updateConnection,
} from '@/actions/App/Http/Controllers/Maacc/SsoConnectionController';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    Field,
    Input,
    Modal,
    PageHeader,
    Select,
    Table,
    Td,
    Toggle,
    Tr,
} from '@/components/maacc/ui';
import type { Tone } from '@/components/maacc/ui';
import { FieldError } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';
import type { MaaccSsoConnection } from '@/types/global';

const PROVIDER_OPTIONS = [
    { value: 'oidc', label: 'OpenID Connect' },
    { value: 'oauth2', label: 'OAuth 2.0' },
];
const TEAM_ROLE_OPTIONS = [
    { value: 'member', label: 'Member' },
    { value: 'admin', label: 'Admin' },
    { value: 'owner', label: 'Owner' },
];
const MAACC_ROLE_OPTIONS = [
    { value: 'none', label: '— (no project role)' },
    { value: 'project_owner', label: 'Project Owner' },
    { value: 'developer', label: 'Developer' },
    { value: 'viewer', label: 'Viewer' },
    { value: 'auditor', label: 'Auditor' },
    { value: 'security_reviewer', label: 'Security Reviewer' },
];

type Mapping = {
    group: string;
    team_role: string;
    maacc_role?: string;
    project_slug?: string;
};

function ConnectionFormModal({
    connection,
    open,
    onClose,
}: {
    connection?: MaaccSsoConnection;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const isEdit = !!connection;
    const form = useForm<{
        name: string;
        provider: string;
        authorize_url: string;
        token_url: string;
        userinfo_url: string;
        client_id: string;
        client_secret: string;
        scopes: string;
        groups_claim: string;
        default_team_role: string;
        group_role_mappings: Mapping[];
        auto_provision: boolean;
        status: string;
    }>({
        name: connection?.name ?? '',
        provider: connection?.provider ?? 'oidc',
        authorize_url: connection?.authorizeUrl ?? '',
        token_url: connection?.tokenUrl ?? '',
        userinfo_url: connection?.userinfoUrl ?? '',
        client_id: connection?.clientId ?? '',
        client_secret: '',
        scopes: connection?.scopes ?? 'openid profile email groups',
        groups_claim: connection?.groupsClaim ?? 'groups',
        default_team_role: connection?.defaultTeamRole ?? 'member',
        group_role_mappings: connection?.groupRoleMappings ?? [],
        auto_provision: connection?.autoProvision ?? true,
        status: connection?.status ?? 'active',
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const setMapping = (index: number, key: keyof Mapping, value: string) => {
        const next = [...form.data.group_role_mappings];
        next[index] = { ...next[index], [key]: value };
        form.setData('group_role_mappings', next);
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.transform((data) => ({
            ...data,
            group_role_mappings: data.group_role_mappings.map((m) => ({
                group: m.group,
                team_role: m.team_role,
                ...(m.maacc_role && m.maacc_role !== 'none'
                    ? { maacc_role: m.maacc_role }
                    : {}),
                ...(m.project_slug ? { project_slug: m.project_slug } : {}),
            })),
        }));

        if (connection) {
            form.put(updateConnection([currentTeam.slug, connection.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeConnection([currentTeam.slug]).url, {
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
            icon="lock"
            title={
                isEdit
                    ? 'Edit identity connection'
                    : 'Register identity connection'
            }
            sub="Web users authenticate through this provider; the group → role rules map their identity onto MAACC roles."
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
                        {isEdit ? 'Save changes' : 'Register connection'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div style={half}>
                    <Field label="Connection name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Milaha Entra ID"
                        />
                        <FieldError error={form.errors.name} />
                    </Field>
                    <Field label="Protocol" required>
                        <Select
                            value={form.data.provider}
                            onChange={(v) => form.setData('provider', v)}
                            options={PROVIDER_OPTIONS}
                        />
                        <FieldError error={form.errors.provider} />
                    </Field>
                </div>
                <Field label="Authorize URL" required>
                    <Input
                        value={form.data.authorize_url}
                        onChange={(e) =>
                            form.setData('authorize_url', e.target.value)
                        }
                        placeholder="https://login.example.com/authorize"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.authorize_url} />
                </Field>
                <div style={half}>
                    <Field label="Token URL" required>
                        <Input
                            value={form.data.token_url}
                            onChange={(e) =>
                                form.setData('token_url', e.target.value)
                            }
                            placeholder="https://login.example.com/token"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.token_url} />
                    </Field>
                    <Field label="Userinfo URL" required>
                        <Input
                            value={form.data.userinfo_url}
                            onChange={(e) =>
                                form.setData('userinfo_url', e.target.value)
                            }
                            placeholder="https://login.example.com/userinfo"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.userinfo_url} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Client ID" required>
                        <Input
                            value={form.data.client_id}
                            onChange={(e) =>
                                form.setData('client_id', e.target.value)
                            }
                            placeholder="application-client-id"
                        />
                        <FieldError error={form.errors.client_id} />
                    </Field>
                    <Field
                        label={
                            isEdit
                                ? 'Client secret (leave blank to keep)'
                                : 'Client secret'
                        }
                        required={!isEdit}
                        hint="Stored encrypted, never displayed again."
                    >
                        <Input
                            type="password"
                            value={form.data.client_secret}
                            onChange={(e) =>
                                form.setData('client_secret', e.target.value)
                            }
                            placeholder={
                                isEdit && connection?.secretConfigured
                                    ? '•••••••• (unchanged)'
                                    : 'client secret'
                            }
                        />
                        <FieldError error={form.errors.client_secret} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Scopes" hint="Space-separated.">
                        <Input
                            value={form.data.scopes}
                            onChange={(e) =>
                                form.setData('scopes', e.target.value)
                            }
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.scopes} />
                    </Field>
                    <Field
                        label="Default team role"
                        required
                        hint="When no group matches."
                    >
                        <Select
                            value={form.data.default_team_role}
                            onChange={(v) =>
                                form.setData('default_team_role', v)
                            }
                            options={TEAM_ROLE_OPTIONS}
                        />
                        <FieldError error={form.errors.default_team_role} />
                    </Field>
                </div>
                <Field
                    label="Group → role mappings"
                    hint="Map an external group claim to a MAACC team role (and optionally a project role)."
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        {form.data.group_role_mappings.map((m, i) => (
                            <div
                                key={i}
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        '1.4fr 1fr 1.2fr 1.2fr auto',
                                    gap: 6,
                                    alignItems: 'center',
                                }}
                            >
                                <Input
                                    value={m.group}
                                    onChange={(e) =>
                                        setMapping(i, 'group', e.target.value)
                                    }
                                    placeholder="IdP group"
                                />
                                <Select
                                    value={m.team_role}
                                    onChange={(v) =>
                                        setMapping(i, 'team_role', v)
                                    }
                                    options={TEAM_ROLE_OPTIONS}
                                />
                                <Select
                                    value={m.maacc_role || 'none'}
                                    onChange={(v) =>
                                        setMapping(i, 'maacc_role', v)
                                    }
                                    options={MAACC_ROLE_OPTIONS}
                                />
                                <Input
                                    value={m.project_slug ?? ''}
                                    onChange={(e) =>
                                        setMapping(
                                            i,
                                            'project_slug',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="project slug"
                                />
                                <IconBtn
                                    icon="trash"
                                    title="Remove"
                                    danger
                                    onClick={() =>
                                        form.setData(
                                            'group_role_mappings',
                                            form.data.group_role_mappings.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <Btn
                            variant="ghost"
                            icon="plus"
                            onClick={() =>
                                form.setData('group_role_mappings', [
                                    ...form.data.group_role_mappings,
                                    { group: '', team_role: 'member' },
                                ])
                            }
                        >
                            Add mapping
                        </Btn>
                    </div>
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
                            Auto-provision users
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            Create a MAACC account on first login for an unknown
                            identity.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.auto_provision}
                        onChange={(v) => form.setData('auto_provision', v)}
                    />
                </div>
            </div>
        </Modal>
    );
}

export default function Identity() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const connections = MAACC.ssoConnections;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaaccSsoConnection | undefined>();

    const active = connections.filter((c) => c.status === 'active').length;
    const users = connections.reduce(
        (sum, c) => sum + (c.identityCount ?? 0),
        0,
    );

    const toggle = (connection: MaaccSsoConnection) => {
        router.put(
            updateConnection([teamSlug, connection.id]).url,
            { status: connection.status === 'active' ? 'disabled' : 'active' },
            { preserveScroll: true },
        );
    };

    const remove = (connection: MaaccSsoConnection) => {
        if (
            window.confirm(`Delete the identity connection ${connection.name}?`)
        ) {
            router.delete(destroyConnection([teamSlug, connection.id]).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Enterprise Identity" />
            <div className="route-anim">
                <PageHeader
                    title="Enterprise Identity"
                    sub="Register the OAuth 2.0 / OIDC providers your web users sign in through. Each connection maps external groups onto MAACC team and project roles; every SSO login is recorded in the audit log. Local password sign-in remains available."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => {
                                setEditing(undefined);
                                setModalOpen(true);
                            }}
                        >
                            Register connection
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
                        label="Connections"
                        value={connections.length}
                        icon="lock"
                    />
                    <Stat
                        label="Active"
                        value={active}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Linked identities"
                        value={users}
                        icon="user"
                        tone="purple"
                    />
                </div>

                {connections.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="lock"
                            title="No identity connections yet"
                            desc="Register an enterprise identity provider so your team can sign in with SSO and receive roles mapped from their directory groups."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={() => {
                                        setEditing(undefined);
                                        setModalOpen(true);
                                    }}
                                >
                                    Register connection
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Connection' },
                            { label: 'Protocol' },
                            { label: 'Callback (register with IdP)' },
                            { label: 'Identities', align: 'center' },
                            { label: 'Active', align: 'center' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {connections.map((c) => (
                            <Tr key={c.id}>
                                <Td strong>{c.name}</Td>
                                <Td>
                                    <Badge tone="blue" soft>
                                        {c.providerLabel}
                                    </Badge>
                                </Td>
                                <Td>
                                    <span
                                        style={{
                                            fontFamily: 'var(--mono)',
                                            fontSize: 11,
                                            wordBreak: 'break-all',
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {c.redirectUri}
                                    </span>
                                </Td>
                                <Td align="center">{c.identityCount ?? 0}</Td>
                                <Td align="center">
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Toggle
                                            on={c.status === 'active'}
                                            onChange={() => toggle(c)}
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
                                                setEditing(c);
                                                setModalOpen(true);
                                            }}
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

                <ConnectionFormModal
                    connection={editing}
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
