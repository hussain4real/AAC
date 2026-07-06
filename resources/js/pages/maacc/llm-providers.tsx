/* ============================================================
   MAACC — LLM Providers
   ============================================================ */
import { Head, router, useForm, useHttp } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    destroy as destroyLlm,
    publish as publishLlm,
    store as storeLlm,
    update as updateLlm,
    verify as verifyLlm,
} from '@/actions/App/Http/Controllers/Maacc/LlmProviderController';
import { Donut, DonutLegend, StatCard } from '@/components/maacc/charts';
import {
    Badge,
    Btn,
    Card,
    Field,
    Input,
    Modal,
    PageHeader,
    SectionHeader,
    Select,
    SensBadge,
    Table,
    Td,
    Textarea,
    Tr,
} from '@/components/maacc/ui';
import type { Llm, LlmVerification, ProviderCatalogEntry } from '@/maacc/data';
import {
    ChipMultiSelect,
    ENV_OPTIONS,
    FieldError,
    SENSITIVITY_OPTIONS,
    toEnumValue,
    useCurrentTeam,
} from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';

/** The JSON returned by the verify endpoint's `result` key. */
type VerifyResult = {
    outcome: string;
    label: string;
    focus: string;
    message: string;
    passed: boolean;
    latency_ms: number | null;
};

/** Status options an operator may set directly — `approved` is reached only
 * through the verification-gated publish action. */
const EDITABLE_STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'deprecated', label: 'Deprecated' },
    { value: 'blocked', label: 'Blocked' },
];

/** Sentinel for the "custom model" option — Radix `Select` forbids an
 * empty-string item value, so the escape hatch needs a real value. */
const CUSTOM_MODEL = '__custom__';

/** Map a verification focus to a badge tone. */
function focusTone(focus?: string | null): 'teal' | 'amber' | 'red' {
    if (focus === 'none') {
        return 'teal';
    }

    return focus === 'network' || focus === 'retry' ? 'amber' : 'red';
}

/** Compact verification indicator shown in the model catalog row. */
function VerificationChip({ v }: { v?: LlmVerification }) {
    if (!v || !v.status) {
        return (
            <span style={{ fontSize: 10.5, color: 'var(--text-3)' }}>
                Unverified
            </span>
        );
    }

    const ok = v.status === 'ok';

    return (
        <span
            className="maacc-tip"
            data-tip={v.message ?? undefined}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                fontSize: 10.5,
                color: ok ? 'var(--teal-500)' : 'var(--red-500)',
            }}
        >
            <Icon name={ok ? 'checkCircle' : 'x'} size={11} />
            {ok ? 'Verified' : (v.label ?? 'Failed')}
        </span>
    );
}

function LlmFormModal({
    llm,
    catalog,
    open,
    onClose,
}: {
    llm?: Llm;
    catalog: ProviderCatalogEntry[];
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const isEdit = !!llm;
    const { submit } = useHttp();
    const [testing, setTesting] = useState(false);
    const [result, setResult] = useState<VerifyResult | null>(null);

    const form = useForm<{
        name: string;
        code: string;
        provider: string;
        context_window: string;
        input_cost: number;
        output_cost: number;
        sensitivity: string;
        environments: string[];
        status: string;
        api_key: string;
        note: string;
    }>({
        name: llm?.name ?? '',
        code: llm?.code ?? '',
        provider: llm?.provider ?? catalog[0]?.label ?? '',
        context_window: llm?.ctx ?? '',
        input_cost: llm?.inCost ?? 0,
        output_cost: llm?.outCost ?? 0,
        sensitivity: llm ? toEnumValue(llm.sensitivity) : 'internal',
        environments: llm
            ? llm.envs.map((e) => toEnumValue(e))
            : ['development'],
        status: llm ? toEnumValue(llm.status) : 'draft',
        api_key: '',
        note: llm?.note ?? '',
    });

    // Provider options come from the curated catalog; a legacy/custom provider
    // that is not in the catalog is preserved as its own option.
    const providerOptions = useMemo(() => {
        const opts = catalog.map((c) => ({ value: c.label, label: c.label }));

        if (
            form.data.provider &&
            !opts.some((o) => o.value === form.data.provider)
        ) {
            opts.push({ value: form.data.provider, label: form.data.provider });
        }

        return opts;
    }, [catalog, form.data.provider]);

    const activeProvider = catalog.find((c) => c.label === form.data.provider);
    const models = activeProvider?.models ?? [];
    const modelOptions = [
        ...models.map((m) => ({ value: m.code, label: m.label })),
        { value: CUSTOM_MODEL, label: 'Custom / other…' },
    ];
    const selectedModel = models.some((m) => m.code === form.data.code)
        ? form.data.code
        : CUSTOM_MODEL;
    const displayCode = activeProvider
        ? `${activeProvider.driver}/${form.data.code || '…'}`
        : form.data.code;

    const pickModel = (code: string) => {
        const model = models.find((m) => m.code === code);

        if (!model) {
            return;
        }

        form.setData({
            ...form.data,
            code: model.code,
            context_window: model.context,
            input_cost: model.input,
            output_cost: model.output,
            name: form.data.name || model.label,
        });
    };

    const close = () => {
        form.clearErrors();
        setResult(null);
        onClose();
    };

    const toggleEnv = (value: string) => {
        form.setData(
            'environments',
            form.data.environments.includes(value)
                ? form.data.environments.filter((e) => e !== value)
                : [...form.data.environments, value],
        );
    };

    const submitForm = () => {
        if (!team) {
            return;
        }

        if (llm) {
            form.put(updateLlm([team.slug, llm.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeLlm([team.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    // Save the current form, then run a live connection check so the result
    // reflects exactly what was entered.
    const testConnection = () => {
        if (!team || !llm) {
            return;
        }

        setResult(null);
        setTesting(true);

        form.put(updateLlm([team.slug, llm.id]).url, {
            preserveScroll: true,
            onSuccess: async () => {
                try {
                    const data = (await submit(
                        verifyLlm([team.slug, llm.id]),
                    )) as { result: VerifyResult };
                    setResult(data.result);
                    router.reload({ only: ['maacc'] });
                } catch {
                    setResult({
                        outcome: 'unknown',
                        label: 'Check failed',
                        focus: 'retry',
                        message: 'The connection check could not be completed.',
                        passed: false,
                        latency_ms: null,
                    });
                } finally {
                    setTesting(false);
                }
            },
            onError: () => setTesting(false),
        });
    };

    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="llm"
            title={isEdit ? 'Edit model' : 'Add model'}
            sub="Pick a provider and model, add its API key, then verify the live connection before publishing."
            width={620}
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    {isEdit && (
                        <Btn
                            variant="ghost"
                            icon="bolt"
                            disabled={form.processing || testing}
                            onClick={testConnection}
                        >
                            {testing ? 'Testing…' : 'Test connection'}
                        </Btn>
                    )}
                    <Btn
                        variant="primary"
                        icon="check"
                        disabled={form.processing || testing}
                        onClick={submitForm}
                    >
                        {isEdit ? 'Save changes' : 'Add model'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div style={half}>
                    <Field label="Provider" required>
                        <Select
                            value={form.data.provider}
                            onChange={(v) => form.setData('provider', v)}
                            options={providerOptions}
                        />
                        <FieldError error={form.errors.provider} />
                    </Field>
                    <Field label="Model" hint="From the provider catalog">
                        <Select
                            value={selectedModel}
                            onChange={pickModel}
                            options={modelOptions}
                        />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Model name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="GPT-5.4"
                        />
                        <FieldError error={form.errors.name} />
                    </Field>
                    <Field
                        label="Model code"
                        required
                        hint="The bare id the provider expects"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            placeholder="gpt-5.4"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.code} />
                    </Field>
                </div>
                <div
                    className="mono"
                    style={{
                        fontSize: 11,
                        color: 'var(--text-3)',
                        marginTop: -6,
                    }}
                >
                    Sent to the provider as{' '}
                    <span style={{ color: 'var(--text-2)' }}>
                        {form.data.code || '…'}
                    </span>{' '}
                    · shown as {displayCode}
                </div>
                <Field
                    label="API key"
                    hint={
                        llm?.hasKey
                            ? `Key set ••••${llm.keyLastFour ?? ''} — leave blank to keep`
                            : 'Stored encrypted in the vault'
                    }
                >
                    <Input
                        type="password"
                        value={form.data.api_key}
                        onChange={(e) =>
                            form.setData('api_key', e.target.value)
                        }
                        placeholder={llm?.hasKey ? '••••••••' : 'sk-…'}
                        autoComplete="off"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.api_key} />
                </Field>
                <div style={half}>
                    <Field label="Context window" required>
                        <Input
                            value={form.data.context_window}
                            onChange={(e) =>
                                form.setData('context_window', e.target.value)
                            }
                            placeholder="400K"
                        />
                        <FieldError error={form.errors.context_window} />
                    </Field>
                    <Field label="Sensitivity rating" required>
                        <Select
                            value={form.data.sensitivity}
                            onChange={(v) => form.setData('sensitivity', v)}
                            options={SENSITIVITY_OPTIONS}
                        />
                        <FieldError error={form.errors.sensitivity} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Input cost / 1M" required>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.input_cost}
                            onChange={(e) =>
                                form.setData(
                                    'input_cost',
                                    parseFloat(e.target.value) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.input_cost} />
                    </Field>
                    <Field label="Output cost / 1M" required>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.output_cost}
                            onChange={(e) =>
                                form.setData(
                                    'output_cost',
                                    parseFloat(e.target.value) || 0,
                                )
                            }
                        />
                        <FieldError error={form.errors.output_cost} />
                    </Field>
                </div>
                {isEdit && (
                    <Field label="Status" required>
                        <Select
                            value={form.data.status}
                            onChange={(v) => form.setData('status', v)}
                            options={EDITABLE_STATUS_OPTIONS}
                        />
                        <FieldError error={form.errors.status} />
                    </Field>
                )}
                <Field label="Allowed environments" required>
                    <ChipMultiSelect
                        options={ENV_OPTIONS}
                        selected={form.data.environments}
                        onToggle={toggleEnv}
                    />
                    <FieldError error={form.errors.environments} />
                </Field>
                <Field label="Notes">
                    <Textarea
                        rows={2}
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        placeholder="Usage guidance for this model."
                    />
                    <FieldError error={form.errors.note} />
                </Field>
                {result && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: 9,
                            padding: '10px 12px',
                            borderRadius: 10,
                            border: `1px solid var(--${result.passed ? 'teal' : focusTone(result.focus)}-500)`,
                            background: result.passed
                                ? 'color-mix(in srgb, var(--teal-500) 10%, transparent)'
                                : 'color-mix(in srgb, var(--red-500) 8%, transparent)',
                        }}
                    >
                        <Icon
                            name={result.passed ? 'checkCircle' : 'alert'}
                            size={15}
                        />
                        <div>
                            <div
                                style={{
                                    fontWeight: 600,
                                    fontSize: 12.5,
                                    color: 'var(--text)',
                                }}
                            >
                                {result.label}
                                {result.passed && result.latency_ms !== null
                                    ? ` · ${result.latency_ms}ms`
                                    : ''}
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.5,
                                }}
                            >
                                {result.message}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}

export default function LLMProviders() {
    const MAACC = useMaaccData();
    const team = useCurrentTeam();
    const [showAdd, setShowAdd] = useState(false);
    const [editing, setEditing] = useState<Llm | null>(null);
    const approved = MAACC.llms.filter((l) => l.status === 'Approved');
    const totalRuns = MAACC.llms.reduce((s, l) => s + l.runs, 0);

    const publishModel = (llm: Llm) => {
        if (team) {
            router.post(
                publishLlm([team.slug, llm.id]).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    const remove = (llm: Llm) => {
        if (team && window.confirm(`Remove ${llm.name} from the catalog?`)) {
            router.delete(destroyLlm([team.slug, llm.id]).url, {
                preserveScroll: true,
            });
        }
    };

    const statusTone = (status: Llm['status']) =>
        status === 'Approved'
            ? 'teal'
            : status === 'Deprecated'
              ? 'amber'
              : status === 'Blocked'
                ? 'red'
                : 'blue';

    return (
        <>
            <Head title="LLM Providers" />
            <div className="route-anim">
                <PageHeader
                    title="LLM Providers"
                    sub="Company-approved model catalog. Admins control which models are enabled, in which environments, and for which sensitivity levels."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => setShowAdd(true)}
                        >
                            Add Model
                        </Btn>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <StatCard
                        label="Approved models"
                        value={approved.length}
                        icon="check2"
                        tone="teal"
                    />
                    <StatCard
                        label="Providers"
                        value={new Set(MAACC.llms.map((l) => l.provider)).size}
                        icon="llm"
                        tone="purple"
                    />
                    <StatCard
                        label="Runs (7d)"
                        value={(totalRuns / 1000).toFixed(1) + 'K'}
                        icon="runs"
                        tone="blue"
                    />
                    <StatCard
                        label="On-prem models"
                        value={
                            MAACC.llms.filter((l) =>
                                l.provider.includes('On-Prem'),
                            ).length
                        }
                        icon="lock"
                        tone="amber"
                        sub="No data egress"
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 320px',
                        gap: 14,
                        marginBottom: 14,
                    }}
                >
                    <Card pad={false}>
                        <div style={{ padding: '14px 16px 12px' }}>
                            <SectionHeader
                                title="Model catalog"
                                icon="llm"
                                style={{ marginBottom: 0 }}
                            />
                        </div>
                        <Table
                            columns={[
                                { label: 'Model' },
                                { label: 'Context' },
                                { label: 'Cost / 1M', align: 'right' },
                                { label: 'Sensitivity' },
                                { label: 'Environments' },
                                { label: 'Status' },
                                { label: '' },
                            ]}
                        >
                            {MAACC.llms.map((l) => (
                                <Tr key={l.id}>
                                    <Td strong>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    width: 30,
                                                    height: 30,
                                                    borderRadius: 8,
                                                    background:
                                                        'var(--navy-900)',
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    flexShrink: 0,
                                                }}
                                            >
                                                <Icon name="llm" size={15} />
                                            </span>
                                            <div>
                                                <div
                                                    style={{
                                                        color: 'var(--text)',
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {l.name}
                                                </div>
                                                <div
                                                    className="mono"
                                                    style={{
                                                        fontSize: 11,
                                                        color: 'var(--text-3)',
                                                    }}
                                                >
                                                    {l.provider}
                                                </div>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td mono>{l.ctx}</Td>
                                    <Td align="right" mono>
                                        ${l.inCost} / ${l.outCost}
                                    </Td>
                                    <Td>
                                        <SensBadge level={l.sensitivity} />
                                    </Td>
                                    <Td>
                                        <div
                                            style={{ display: 'flex', gap: 4 }}
                                        >
                                            {(
                                                [
                                                    'Production',
                                                    'Staging',
                                                    'Development',
                                                ] as const
                                            ).map((e) => (
                                                <span
                                                    key={e}
                                                    className="maacc-tip"
                                                    data-tip={e}
                                                    style={{
                                                        width: 8,
                                                        height: 8,
                                                        borderRadius: 8,
                                                        background:
                                                            l.envs.includes(e)
                                                                ? 'var(--teal-500)'
                                                                : 'var(--border-2)',
                                                    }}
                                                />
                                            ))}
                                        </div>
                                    </Td>
                                    <Td>
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 3,
                                                alignItems: 'flex-start',
                                            }}
                                        >
                                            <Badge
                                                tone={statusTone(l.status)}
                                                dot
                                            >
                                                {l.status}
                                            </Badge>
                                            <VerificationChip
                                                v={l.verification}
                                            />
                                        </div>
                                    </Td>
                                    <Td align="right">
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                alignItems: 'center',
                                                justifyContent: 'flex-end',
                                            }}
                                        >
                                            {l.status !== 'Approved' && (
                                                <Btn
                                                    variant="primary"
                                                    size="sm"
                                                    icon="checkCircle"
                                                    onClick={() =>
                                                        publishModel(l)
                                                    }
                                                >
                                                    Publish
                                                </Btn>
                                            )}
                                            <Btn
                                                variant="ghost"
                                                size="icon"
                                                icon="edit"
                                                style={{
                                                    height: 28,
                                                    width: 28,
                                                }}
                                                onClick={() => setEditing(l)}
                                            />
                                            <Btn
                                                variant="ghost"
                                                size="icon"
                                                icon="trash"
                                                style={{
                                                    height: 28,
                                                    width: 28,
                                                }}
                                                onClick={() => remove(l)}
                                            />
                                        </div>
                                    </Td>
                                </Tr>
                            ))}
                        </Table>
                    </Card>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader
                                title="Usage by model"
                                sub="Share of runs (7d)"
                                icon="layers"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 16,
                                    marginBottom: 14,
                                }}
                            >
                                <Donut
                                    size={120}
                                    thickness={15}
                                    centerLabel={
                                        (totalRuns / 1000).toFixed(0) + 'K'
                                    }
                                    centerSub="runs"
                                    data={approved.slice(0, 5).map((l, i) => ({
                                        label: l.name,
                                        value: l.runs,
                                        color: [
                                            'var(--purple-600)',
                                            'var(--purple-500)',
                                            'var(--teal-500)',
                                            'var(--blue-500)',
                                            'var(--orange-500)',
                                        ][i],
                                    }))}
                                />
                            </div>
                            <DonutLegend
                                data={approved.slice(0, 5).map((l, i) => ({
                                    label: l.name,
                                    value: l.runs,
                                    color: [
                                        'var(--purple-600)',
                                        'var(--purple-500)',
                                        'var(--teal-500)',
                                        'var(--blue-500)',
                                        'var(--orange-500)',
                                    ][i],
                                }))}
                            />
                        </Card>
                        <Card>
                            <SectionHeader
                                title="Sensitivity routing"
                                icon="shield"
                            />
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.55,
                                    marginBottom: 12,
                                }}
                            >
                                Models are restricted by the data sensitivity
                                they may process.
                            </div>
                            {MAACC.sensitivityLevels.map((s) => (
                                <div
                                    key={s.name}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 9,
                                        padding: '7px 0',
                                        borderTop: '1px solid var(--border)',
                                    }}
                                >
                                    <SensBadge level={s.name} />
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--text-3)',
                                            flex: 1,
                                        }}
                                    >
                                        {s.name === 'Restricted'
                                            ? 'On-prem only'
                                            : s.name === 'Confidential'
                                              ? 'Approved region'
                                              : 'Any approved'}
                                    </span>
                                </div>
                            ))}
                        </Card>
                    </div>
                </div>

                <LlmFormModal
                    catalog={MAACC.providerCatalog}
                    open={showAdd}
                    onClose={() => setShowAdd(false)}
                />
                {editing && (
                    <LlmFormModal
                        key={editing.id}
                        llm={editing}
                        catalog={MAACC.providerCatalog}
                        open
                        onClose={() => setEditing(null)}
                    />
                )}
            </div>
        </>
    );
}
