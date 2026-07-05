/* ============================================================
   MAACC — Secrets Vault (Phase 6G)
   The governed system of record for sensitive credential material
   (LLM keys, application credentials, remote tool secrets, webhook
   and connector credentials). Plaintext is write-only — stored
   encrypted and never re-displayed. LLM-key secrets bind to an
   approved model so the runtime resolves the key from the vault.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroySecret,
    rotate as rotateSecret,
    store as storeSecret,
} from '@/actions/App/Http/Controllers/Maacc/VaultSecretController';
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
    Tr,
} from '@/components/maacc/ui';
import type { Tone } from '@/components/maacc/ui';
import { FieldError } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';
import type { MaaccVaultSecret } from '@/types/global';

const KIND_OPTIONS = [
    { value: 'llm_key', label: 'LLM Provider Key' },
    { value: 'credential', label: 'Application Credential' },
    { value: 'http_tool', label: 'Remote HTTP Tool Secret' },
    { value: 'webhook', label: 'Webhook Signing Secret' },
    { value: 'connector', label: 'MCP Connector Credential' },
    { value: 'generic', label: 'Generic Secret' },
];

function StoreSecretModal({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const form = useForm<{
        name: string;
        kind: string;
        value: string;
        llm_provider_id: string;
    }>({ name: '', kind: 'generic', value: '', llm_provider_id: 'none' });

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
            llm_provider_id:
                data.kind === 'llm_key' && data.llm_provider_id !== 'none'
                    ? data.llm_provider_id
                    : null,
        }));
        form.post(storeSecret([currentTeam.slug]).url, {
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
            icon="key"
            title="Store a secret"
            sub="Secret material is encrypted at rest and never displayed again."
            width={560}
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
                        Store secret
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Anthropic production key"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <Field label="Kind" required>
                    <Select
                        value={form.data.kind}
                        onChange={(v) => form.setData('kind', v)}
                        options={KIND_OPTIONS}
                    />
                    <FieldError error={form.errors.kind} />
                </Field>
                {form.data.kind === 'llm_key' && (
                    <Field
                        label="Bind to model"
                        required
                        hint="The runtime resolves this model's API key from the vault."
                    >
                        <Select
                            value={form.data.llm_provider_id}
                            onChange={(v) => form.setData('llm_provider_id', v)}
                            options={[
                                { value: 'none', label: 'Select a model…' },
                                ...MAACC.llms.map((l) => ({
                                    value: l.uuid ?? '',
                                    label: l.name,
                                })),
                            ]}
                        />
                        <FieldError error={form.errors.llm_provider_id} />
                    </Field>
                )}
                <Field
                    label="Secret value"
                    required
                    hint="Stored encrypted; shown to no one after saving."
                >
                    <Input
                        type="password"
                        value={form.data.value}
                        onChange={(e) => form.setData('value', e.target.value)}
                        placeholder="paste the secret value"
                    />
                    <FieldError error={form.errors.value} />
                </Field>
            </div>
        </Modal>
    );
}

function RotateSecretModal({
    secret,
    onClose,
}: {
    secret: MaaccVaultSecret | null;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const form = useForm<{ value: string }>({ value: '' });

    const submit = () => {
        if (!currentTeam || !secret) {
            return;
        }

        form.post(rotateSecret([currentTeam.slug, secret.id]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={!!secret}
            onClose={onClose}
            icon="refresh"
            title={secret ? `Rotate ${secret.name}` : 'Rotate secret'}
            sub="Replace the stored material with a new value. The next runtime use picks it up immediately."
            width={480}
            footer={
                <>
                    <Btn variant="ghost" onClick={onClose}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="refresh"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Rotate secret
                    </Btn>
                </>
            }
        >
            <Field label="New secret value" required>
                <Input
                    type="password"
                    value={form.data.value}
                    onChange={(e) => form.setData('value', e.target.value)}
                    placeholder="paste the new secret value"
                />
                <FieldError error={form.errors.value} />
            </Field>
        </Modal>
    );
}

export default function Vault() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const secrets = MAACC.vaultSecrets;
    const [storeOpen, setStoreOpen] = useState(false);
    const [rotating, setRotating] = useState<MaaccVaultSecret | null>(null);

    const bound = secrets.filter((s) => s.boundModel.length > 0).length;
    const rotated = secrets.filter((s) => s.version > 1).length;

    const remove = (secret: MaaccVaultSecret) => {
        if (
            window.confirm(
                `Forget the secret ${secret.name}? Any bound models fall back to the environment key.`,
            )
        ) {
            router.delete(destroySecret([teamSlug, secret.id]).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Secrets Vault" />
            <div className="route-anim">
                <PageHeader
                    title="Secrets Vault"
                    sub="The governed system of record for the platform's sensitive credential material. Secrets are encrypted at rest, versioned on rotation, and access is tracked — the plaintext is never shown again after it is stored."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => setStoreOpen(true)}
                        >
                            Store secret
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
                    <Stat label="Secrets" value={secrets.length} icon="key" />
                    <Stat
                        label="Bound to a model"
                        value={bound}
                        icon="link"
                        tone="teal"
                    />
                    <Stat
                        label="Rotated"
                        value={rotated}
                        icon="refresh"
                        tone="orange"
                    />
                </div>

                {secrets.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="key"
                            title="No secrets stored yet"
                            desc="Store a provider key, credential, or connector secret. LLM-key secrets bind to an approved model so the runtime resolves the key from the vault."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={() => setStoreOpen(true)}
                                >
                                    Store secret
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Secret' },
                            { label: 'Kind' },
                            { label: 'Bound to' },
                            { label: 'Version', align: 'center' },
                            { label: 'Last used' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {secrets.map((s) => (
                            <Tr key={s.id}>
                                <Td strong>
                                    {s.name}
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--text-3)',
                                            fontFamily: 'var(--mono)',
                                        }}
                                    >
                                        ••••{s.lastFour}
                                    </div>
                                </Td>
                                <Td>
                                    <Badge tone="purple" soft>
                                        {s.kindLabel}
                                    </Badge>
                                </Td>
                                <Td>
                                    {s.boundModel.length > 0
                                        ? s.boundModel.join(', ')
                                        : '—'}
                                </Td>
                                <Td align="center">v{s.version}</Td>
                                <Td>{s.lastAccessed ?? 'Never'}</Td>
                                <Td align="right">
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            gap: 6,
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        <IconBtn
                                            icon="refresh"
                                            title="Rotate"
                                            onClick={() => setRotating(s)}
                                        />
                                        <IconBtn
                                            icon="trash"
                                            title="Forget"
                                            danger
                                            onClick={() => remove(s)}
                                        />
                                    </div>
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                <StoreSecretModal
                    open={storeOpen}
                    onClose={() => setStoreOpen(false)}
                />
                <RotateSecretModal
                    secret={rotating}
                    onClose={() => setRotating(null)}
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
