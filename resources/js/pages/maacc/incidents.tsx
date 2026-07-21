/* ============================================================
   MAACC — Incident Response / Break-Glass (Phase 6G)
   Emergency containment controls: revoke a credential, disable a
   model, shut down a connector, suspend a webhook, or freeze an
   application's runtime. Each action requires a reason, applies
   immediately (bypassing normal approval), and is recorded on the
   immutable incident timeline with a high-severity audit event.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { store as storeIncident } from '@/actions/App/Http/Controllers/Maacc/IncidentController';
import { CursorPagination } from '@/components/maacc/common';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    Field,
    PageHeader,
    SectionHeader,
    Select,
    Table,
    Td,
    Textarea,
    Tr,
    inputStyle,
} from '@/components/maacc/ui';
import type { Tone } from '@/components/maacc/ui';
import { FieldError } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';
import { incidents as incidentsRoute } from '@/routes';

const ACTION_OPTIONS = [
    { value: 'freeze_application', label: 'Freeze application runtime' },
    { value: 'lift_freeze', label: 'Lift runtime freeze' },
    { value: 'disable_model', label: 'Disable model' },
    { value: 'shutdown_connector', label: 'Shut down connector' },
    { value: 'suspend_webhook', label: 'Suspend webhook' },
    { value: 'revoke_credential', label: 'Revoke credential' },
];

const SEVERITY_TONE: Record<string, Tone> = {
    high: 'red',
    med: 'amber',
    low: 'neutral',
};

function BreakGlassPanel() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const form = useForm<{ action: string; target: string; reason: string }>({
        action: 'freeze_application',
        target: 'none',
        reason: '',
    });

    const targets = useMemo<{ value: string; label: string }[]>(() => {
        switch (form.data.action) {
            case 'disable_model':
                return MAACC.llms.map((l) => ({ value: l.id, label: l.name }));
            case 'shutdown_connector':
                return MAACC.connectors.map((c) => ({
                    value: c.id,
                    label: c.name,
                }));
            case 'suspend_webhook':
                return MAACC.webhooks.map((w) => ({
                    value: w.uuid,
                    label: `${w.appName ?? ''} · ${w.url}`,
                }));
            case 'revoke_credential':
                return MAACC.apps.flatMap((a) =>
                    (a.credentials ?? []).map((c) => ({
                        value: c.id,
                        label: `${a.name} / ${c.label}`,
                    })),
                );
            default:
                return MAACC.apps.map((a) => ({ value: a.id, label: a.name }));
        }
    }, [form.data.action, MAACC]);

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        const label =
            ACTION_OPTIONS.find((o) => o.value === form.data.action)?.label ??
            'this control';

        if (
            !window.confirm(
                `Apply break-glass control "${label}" now? This takes effect immediately.`,
            )
        ) {
            return;
        }

        form.post(storeIncident([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    };

    return (
        <Card style={{ marginBottom: 16, borderColor: 'var(--red-600)' }}>
            <SectionHeader
                title="Break-glass controls"
                sub="Contain an active incident immediately. These bypass normal approval — every action is audited."
                icon="shield-alert"
            />
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                    marginTop: 14,
                }}
            >
                <Field label="Control" required>
                    <Select
                        value={form.data.action}
                        onChange={(v) => {
                            form.setData('action', v);
                            form.setData('target', 'none');
                        }}
                        options={ACTION_OPTIONS}
                    />
                    <FieldError error={form.errors.action} />
                </Field>
                <Field label="Target" required>
                    <Select
                        value={form.data.target}
                        onChange={(v) => form.setData('target', v)}
                        options={[
                            {
                                value: 'none',
                                label: targets.length
                                    ? 'Select a target…'
                                    : 'No eligible targets',
                            },
                            ...targets,
                        ]}
                    />
                    <FieldError error={form.errors.target} />
                </Field>
            </div>
            <div style={{ marginTop: 14 }}>
                <Field
                    label="Reason"
                    required
                    hint="Recorded on the incident timeline and the audit log."
                >
                    <Textarea
                        rows={2}
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        placeholder="Why is this control being applied?"
                    />
                    <FieldError error={form.errors.reason} />
                </Field>
            </div>
            <div
                style={{
                    marginTop: 14,
                    display: 'flex',
                    justifyContent: 'flex-end',
                }}
            >
                <Btn
                    variant="danger"
                    icon="shield-alert"
                    disabled={form.processing || form.data.target === 'none'}
                    onClick={submit}
                >
                    Apply control
                </Btn>
            </div>
        </Card>
    );
}

export default function Incidents() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const incidents = MAACC.incidents;
    const frozen = MAACC.apps.filter((a) => a.runtimeFrozen);
    const [query, setQuery] = useState(
        String(MAACC.pagination.incidents?.filters.q ?? ''),
    );

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!currentTeam) {
            return;
        }

        router.get(
            incidentsRoute.url(currentTeam.slug),
            query.trim() ? { q: query.trim() } : {},
            {
                only: ['maacc'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Incident Response" />
            <div className="route-anim">
                <PageHeader
                    title="Incident Response"
                    sub="Break-glass controls and the immutable incident timeline. Use these to contain an incident immediately — revoke a credential, disable a model, shut down a connector, suspend a webhook, or freeze an application's runtime — and review every action a reviewer took."
                />

                <form
                    aria-label="Incident filters"
                    onSubmit={search}
                    style={{
                        display: 'flex',
                        gap: 8,
                        marginBottom: 14,
                        maxWidth: 520,
                    }}
                >
                    <input
                        aria-label="Search incident actions"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search subject or rationale…"
                        className="maacc-input"
                        style={{ ...inputStyle, flex: 1 }}
                    />
                    <Btn type="submit" variant="default" icon="search">
                        Search
                    </Btn>
                </form>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <Stat
                        label="Recorded actions"
                        value={incidents.length}
                        icon="shield"
                    />
                    <Stat
                        label="Frozen applications"
                        value={frozen.length}
                        icon="lock"
                        tone={frozen.length ? 'red' : 'neutral'}
                    />
                    <Stat
                        label="High severity"
                        value={
                            incidents.filter((i) => i.severity === 'high')
                                .length
                        }
                        icon="alert"
                        tone="orange"
                    />
                </div>

                <BreakGlassPanel />

                {frozen.length > 0 && (
                    <Card style={{ marginBottom: 16 }}>
                        <SectionHeader
                            title="Frozen applications"
                            sub="The runtime rejects new and in-flight runs for these applications until the freeze is lifted."
                            icon="lock"
                        />
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                flexWrap: 'wrap',
                                marginTop: 12,
                            }}
                        >
                            {frozen.map((a) => (
                                <Badge key={a.id} tone="red" dot>
                                    {a.name}
                                </Badge>
                            ))}
                        </div>
                    </Card>
                )}

                {incidents.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="shield"
                            title="No incident actions recorded"
                            desc="Break-glass controls you apply will appear here as an immutable, auditable timeline."
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Control' },
                            { label: 'Severity', align: 'center' },
                            { label: 'Subject' },
                            { label: 'Operator' },
                            { label: 'Reason' },
                            { label: 'When' },
                        ]}
                    >
                        {incidents.map((i) => (
                            <Tr key={i.id}>
                                <Td strong>
                                    {i.typeLabel}
                                    {i.reverted && (
                                        <Badge
                                            tone="neutral"
                                            soft
                                            style={{ marginLeft: 8 }}
                                        >
                                            Reverted
                                        </Badge>
                                    )}
                                </Td>
                                <Td align="center">
                                    <Badge
                                        tone={
                                            SEVERITY_TONE[i.severity] ??
                                            'neutral'
                                        }
                                        dot
                                    >
                                        {i.severity}
                                    </Badge>
                                </Td>
                                <Td>{i.subject ?? '—'}</Td>
                                <Td>{i.actor}</Td>
                                <Td>
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: 'var(--text-2)',
                                        }}
                                    >
                                        {i.reason}
                                    </span>
                                </Td>
                                <Td>{i.time}</Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                <CursorPagination
                    pageKey="incidents"
                    cursorName="incidents_cursor"
                    label="Incident actions"
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
