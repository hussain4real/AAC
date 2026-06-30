/* ============================================================
   MAAC — Access Control (platform administration RBAC, Phase 8B)
   MAAC's own internal administration: who holds which global
   platform role, granular permissions, audited grant / revoke /
   break-glass / certify actions, and the access-review work
   lists. Distinct from the team/project-scoped tenant RBAC —
   tenant users hold no platform role by default.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    certify,
    revoke,
    store,
} from '@/actions/App/Http/Controllers/Maac/PlatformAccessController';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    Field,
    Input,
    KV,
    PageHeader,
    SectionHeader,
    Select,
    Table,
    Td,
    Textarea,
    Tr,
} from '@/components/maac/ui';
import type { KVItem, Tone } from '@/components/maac/ui';
import { FieldError } from '@/maac/forms';

type RoleCatalogueEntry = {
    value: string;
    label: string;
    description: string;
    permissions: string[];
    permissionCount: number;
};

type GrantRow = {
    id: string;
    userId: number;
    userName: string;
    userEmail: string;
    role: string;
    roleLabel: string;
    kind: string;
    reason: string | null;
    grantedBy: string;
    expiresAt: string | null;
    certifiedAt: string | null;
    createdAt: string | null;
    isBreakGlass: boolean;
};

type AdminRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    isSuperAdmin: boolean;
    grants: GrantRow[];
};

type AccessReport = {
    roles: RoleCatalogueEntry[];
    permissionGroups: {
        group: string;
        permissions: { value: string; label: string }[];
    }[];
    admins: AdminRow[];
    review: {
        dueForExpiry: GrantRow[];
        needingCertification: GrantRow[];
        stale: GrantRow[];
    };
    audit: {
        id: string;
        action: string;
        actor: string | null;
        metadata: Record<string, unknown> | null;
        at: string | null;
    }[];
};

type DirectoryUser = { id: number; name: string; email: string };

type Capabilities = {
    isSuperAdmin: boolean;
    canAssignRoles: boolean;
    canBreakGlass: boolean;
    canReviewAccess: boolean;
};

const KIND_TONE: Record<string, Tone> = {
    standard: 'teal',
    break_glass: 'orange',
};

function fmt(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

function actionLabel(action: string): string {
    return action.replace('platform_access.', '').replace(/_/g, ' ');
}

export default function AccessControl() {
    const { access, directory, capabilities, currentTeam } = usePage<{
        access: AccessReport;
        directory: DirectoryUser[];
        capabilities: Capabilities;
        currentTeam: { slug: string } | null;
    }>().props;

    const team = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Access Control" />
            <div className="route-anim">
                <PageHeader
                    title="Access Control"
                    sub="MAAC platform administration — the global operator roles, granular permissions, audited grants, and access review. Tenant users hold no platform role by default."
                    badge={
                        <Badge
                            tone={
                                capabilities.isSuperAdmin ? 'purple' : 'neutral'
                            }
                            icon="shield"
                        >
                            {capabilities.isSuperAdmin
                                ? 'Super Admin'
                                : 'Platform admin'}
                        </Badge>
                    }
                />

                <SummaryTiles access={access} />

                {capabilities.canAssignRoles && (
                    <GrantForm
                        team={team}
                        access={access}
                        directory={directory}
                        capabilities={capabilities}
                    />
                )}

                <SectionHeader title="Platform administrators" icon="shield" />
                {access.admins.length === 0 ? (
                    <EmptyState
                        icon="shield"
                        title="No platform administrators"
                        desc="Grant a platform role to give an operator MAAC administration access."
                    />
                ) : (
                    <Card pad={false}>
                        <Table
                            columns={[
                                { label: 'Administrator' },
                                { label: 'Roles' },
                                { label: 'Active grants' },
                                { label: '', align: 'right' },
                            ]}
                        >
                            {access.admins.map((admin) => (
                                <Tr key={admin.id} hover={false}>
                                    <Td>
                                        <div style={{ fontWeight: 600 }}>
                                            {admin.name}
                                        </div>
                                        <div
                                            style={{
                                                color: 'var(--text-2)',
                                                fontSize: 12,
                                            }}
                                        >
                                            {admin.email}
                                        </div>
                                    </Td>
                                    <Td>
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                gap: 4,
                                            }}
                                        >
                                            {admin.roles.map((role) => (
                                                <Badge
                                                    key={role}
                                                    tone={
                                                        role === 'super-admin'
                                                            ? 'purple'
                                                            : 'blue'
                                                    }
                                                >
                                                    {role}
                                                </Badge>
                                            ))}
                                        </div>
                                    </Td>
                                    <Td>
                                        {admin.grants.map((grant) => (
                                            <div
                                                key={grant.id}
                                                style={{
                                                    fontSize: 12,
                                                    marginBottom: 2,
                                                }}
                                            >
                                                <Badge
                                                    tone={KIND_TONE[grant.kind]}
                                                    icon={
                                                        grant.isBreakGlass
                                                            ? 'alert'
                                                            : 'check'
                                                    }
                                                >
                                                    {grant.roleLabel}
                                                </Badge>{' '}
                                                {grant.isBreakGlass &&
                                                    grant.expiresAt && (
                                                        <span
                                                            style={{
                                                                color: 'var(--orange-500)',
                                                            }}
                                                        >
                                                            expires{' '}
                                                            {fmt(
                                                                grant.expiresAt,
                                                            )}
                                                        </span>
                                                    )}
                                            </div>
                                        ))}
                                    </Td>
                                    <Td align="right">
                                        {capabilities.canAssignRoles &&
                                            admin.grants.map((grant) => (
                                                <Btn
                                                    key={grant.id}
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Revoke ${grant.roleLabel} from ${admin.email}?`,
                                                            )
                                                        ) {
                                                            router.post(
                                                                revoke([
                                                                    team,
                                                                    grant.id,
                                                                ]).url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Revoke {grant.role}
                                                </Btn>
                                            ))}
                                    </Td>
                                </Tr>
                            ))}
                        </Table>
                    </Card>
                )}

                <AccessReview
                    team={team}
                    review={access.review}
                    capabilities={capabilities}
                />

                <SectionHeader title="Role catalogue" icon="lock" />
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(300px, 1fr))',
                        gap: 12,
                        marginBottom: 24,
                    }}
                >
                    {access.roles.map((role) => (
                        <Card key={role.value}>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    marginBottom: 6,
                                }}
                            >
                                <strong>{role.label}</strong>
                                <Badge
                                    tone={
                                        role.value === 'super-admin'
                                            ? 'purple'
                                            : 'neutral'
                                    }
                                >
                                    {role.permissionCount} perms
                                </Badge>
                            </div>
                            <div
                                style={{ color: 'var(--text-2)', fontSize: 13 }}
                            >
                                {role.description}
                            </div>
                        </Card>
                    ))}
                </div>

                <SectionHeader title="Platform access audit trail" icon="doc" />
                {access.audit.length === 0 ? (
                    <EmptyState
                        icon="doc"
                        title="No platform-access events yet"
                    />
                ) : (
                    <Card pad={false}>
                        <Table
                            columns={[
                                { label: 'Event' },
                                { label: 'Actor' },
                                { label: 'When' },
                            ]}
                        >
                            {access.audit.map((event) => (
                                <Tr key={event.id} hover={false}>
                                    <Td strong>{actionLabel(event.action)}</Td>
                                    <Td>{event.actor ?? 'system'}</Td>
                                    <Td>{fmt(event.at)}</Td>
                                </Tr>
                            ))}
                        </Table>
                    </Card>
                )}
            </div>
        </>
    );
}

function SummaryTiles({ access }: { access: AccessReport }) {
    const tiles: { label: string; value: number; tone: Tone; icon: string }[] =
        [
            {
                label: 'Platform admins',
                value: access.admins.length,
                tone: 'purple',
                icon: 'shield',
            },
            {
                label: 'Break-glass active',
                value: access.review.dueForExpiry.length,
                tone: 'orange',
                icon: 'alert',
            },
            {
                label: 'Needs certification',
                value: access.review.needingCertification.length,
                tone: 'amber',
                icon: 'clock',
            },
            {
                label: 'Stale admins',
                value: access.review.stale.length,
                tone: access.review.stale.length > 0 ? 'red' : 'teal',
                icon: 'eye',
            },
        ];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: 16,
                marginBottom: 24,
            }}
        >
            {tiles.map((tile) => (
                <Card key={tile.label}>
                    <div style={{ marginBottom: 8 }}>
                        <Badge tone={tile.tone} icon={tile.icon}>
                            {tile.label}
                        </Badge>
                    </div>
                    <div
                        style={{
                            fontSize: 26,
                            fontWeight: 600,
                            color: 'var(--text-1)',
                        }}
                    >
                        {tile.value}
                    </div>
                </Card>
            ))}
        </div>
    );
}

function GrantForm({
    team,
    access,
    directory,
    capabilities,
}: {
    team: string;
    access: AccessReport;
    directory: DirectoryUser[];
    capabilities: Capabilities;
}) {
    const form = useForm<{
        user_id: string;
        role: string;
        kind: string;
        reason: string;
        ttl_minutes: string;
    }>({
        user_id: String(directory[0]?.id ?? ''),
        role: access.roles[0]?.value ?? '',
        kind: 'standard',
        reason: '',
        ttl_minutes: '60',
    });

    const submit = () => {
        if (!team) {
            return;
        }

        form.transform((data) => ({
            ...data,
            user_id: Number(data.user_id),
            ttl_minutes:
                data.kind === 'break_glass' ? Number(data.ttl_minutes) : null,
        }));

        form.post(store([team]).url, {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    };

    const roleOptions = access.roles
        .filter((r) => r.value !== 'super-admin' || capabilities.isSuperAdmin)
        .map((r) => ({ value: r.value, label: r.label }));

    return (
        <Card style={{ marginBottom: 24 }}>
            <SectionHeader title="Grant platform access" />
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                    gap: 12,
                }}
            >
                <Field label="Administrator" required>
                    <Select
                        value={form.data.user_id}
                        onChange={(v) => form.setData('user_id', v)}
                        options={directory.map((u) => ({
                            value: String(u.id),
                            label: `${u.name} (${u.email})`,
                        }))}
                    />
                    <FieldError error={form.errors.user_id} />
                </Field>
                <Field label="Platform role" required>
                    <Select
                        value={form.data.role}
                        onChange={(v) => form.setData('role', v)}
                        options={roleOptions}
                    />
                    <FieldError error={form.errors.role} />
                </Field>
                {capabilities.canBreakGlass && (
                    <Field label="Grant type">
                        <Select
                            value={form.data.kind}
                            onChange={(v) => form.setData('kind', v)}
                            options={[
                                { value: 'standard', label: 'Standard' },
                                {
                                    value: 'break_glass',
                                    label: 'Break-glass (emergency)',
                                },
                            ]}
                        />
                    </Field>
                )}
                {form.data.kind === 'break_glass' && (
                    <Field label="Expires in (minutes)">
                        <Input
                            type="number"
                            min={1}
                            value={form.data.ttl_minutes}
                            onChange={(e) =>
                                form.setData('ttl_minutes', e.target.value)
                            }
                        />
                        <FieldError error={form.errors.ttl_minutes} />
                    </Field>
                )}
            </div>
            <Field label="Reason" required style={{ marginTop: 8 }}>
                <Textarea
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="Why this operator needs this platform role"
                />
                <FieldError error={form.errors.reason} />
            </Field>
            <div style={{ marginTop: 8 }}>
                <Btn
                    variant="primary"
                    icon="plus"
                    onClick={submit}
                    disabled={form.processing}
                >
                    Grant access
                </Btn>
            </div>
        </Card>
    );
}

function AccessReview({
    team,
    review,
    capabilities,
}: {
    team: string;
    review: AccessReport['review'];
    capabilities: Capabilities;
}) {
    const [tab, setTab] = useState<'certification' | 'expiry' | 'stale'>(
        'certification',
    );
    const lists = {
        certification: review.needingCertification,
        expiry: review.dueForExpiry,
        stale: review.stale,
    };
    const rows = lists[tab];

    const tabs: { id: 'certification' | 'expiry' | 'stale'; label: string }[] =
        [
            {
                id: 'certification',
                label: `Needs certification (${review.needingCertification.length})`,
            },
            {
                id: 'expiry',
                label: `Expiring break-glass (${review.dueForExpiry.length})`,
            },
            { id: 'stale', label: `Stale admins (${review.stale.length})` },
        ];

    return (
        <>
            <SectionHeader title="Access review" icon="clock" />
            <div
                style={{
                    display: 'flex',
                    gap: 6,
                    marginBottom: 10,
                    flexWrap: 'wrap',
                }}
            >
                {tabs.map((t) => (
                    <Btn
                        key={t.id}
                        variant={tab === t.id ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setTab(t.id)}
                    >
                        {t.label}
                    </Btn>
                ))}
            </div>
            <Card pad={false} style={{ marginBottom: 24 }}>
                {rows.length === 0 ? (
                    <div style={{ padding: 20 }}>
                        <EmptyState
                            icon="check"
                            title="Nothing to review"
                            desc="No grants in this category."
                        />
                    </div>
                ) : (
                    <Table
                        columns={[
                            { label: 'Administrator' },
                            { label: 'Role' },
                            { label: 'Detail' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {rows.map((grant) => {
                            const detail: KVItem[] = [
                                { k: 'Reason', v: grant.reason ?? '—' },
                                { k: 'Granted by', v: grant.grantedBy },
                            ];

                            return (
                                <Tr key={grant.id} hover={false}>
                                    <Td>{grant.userEmail}</Td>
                                    <Td>
                                        <Badge tone="blue">
                                            {grant.roleLabel}
                                        </Badge>
                                    </Td>
                                    <Td>
                                        <KV cols={1} items={detail} />
                                    </Td>
                                    <Td align="right">
                                        {capabilities.canReviewAccess && (
                                            <Btn
                                                variant="ghost"
                                                size="sm"
                                                icon="check"
                                                onClick={() =>
                                                    router.post(
                                                        certify([
                                                            team,
                                                            grant.id,
                                                        ]).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Certify
                                            </Btn>
                                        )}
                                    </Td>
                                </Tr>
                            );
                        })}
                    </Table>
                )}
            </Card>
        </>
    );
}
