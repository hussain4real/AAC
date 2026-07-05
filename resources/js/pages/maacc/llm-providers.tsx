/* ============================================================
   MAACC — LLM Providers
   ============================================================ */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyLlm,
    store as storeLlm,
    update as updateLlm,
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
    Toggle,
    Tr,
} from '@/components/maacc/ui';
import type { Llm } from '@/maacc/data';
import {
    ChipMultiSelect,
    ENV_OPTIONS,
    FieldError,
    LLM_STATUS_OPTIONS,
    SENSITIVITY_OPTIONS,
    toEnumValue,
    useCurrentTeam,
} from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccData } from '@/maacc/use-data';

function LlmFormModal({
    llm,
    open,
    onClose,
}: {
    llm?: Llm;
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const isEdit = !!llm;
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
        note: string;
    }>({
        name: llm?.name ?? '',
        code: llm?.code ?? '',
        provider: llm?.provider ?? '',
        context_window: llm?.ctx ?? '',
        input_cost: llm?.inCost ?? 0,
        output_cost: llm?.outCost ?? 0,
        sensitivity: llm ? toEnumValue(llm.sensitivity) : 'internal',
        environments: llm
            ? llm.envs.map((e) => toEnumValue(e))
            : ['development'],
        status: llm ? toEnumValue(llm.status) : 'approved',
        note: llm?.note ?? '',
    });

    const close = () => {
        form.clearErrors();
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

    const submit = () => {
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

    const half = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="llm"
            title={isEdit ? 'Edit model' : 'Add Model'}
            sub="Register an approved model in the company catalog."
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
                        {isEdit ? 'Save changes' : 'Add Model'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div style={half}>
                    <Field label="Model name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="GPT-4o"
                        />
                        <FieldError error={form.errors.name} />
                    </Field>
                    <Field label="Model code" required>
                        <Input
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            placeholder="azure/gpt-4o"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={form.errors.code} />
                    </Field>
                </div>
                <div style={half}>
                    <Field label="Provider" required>
                        <Input
                            value={form.data.provider}
                            onChange={(e) =>
                                form.setData('provider', e.target.value)
                            }
                            placeholder="Azure OpenAI"
                        />
                        <FieldError error={form.errors.provider} />
                    </Field>
                    <Field label="Context window" required>
                        <Input
                            value={form.data.context_window}
                            onChange={(e) =>
                                form.setData('context_window', e.target.value)
                            }
                            placeholder="128K"
                        />
                        <FieldError error={form.errors.context_window} />
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
                <div style={half}>
                    <Field label="Sensitivity rating" required>
                        <Select
                            value={form.data.sensitivity}
                            onChange={(v) => form.setData('sensitivity', v)}
                            options={SENSITIVITY_OPTIONS}
                        />
                        <FieldError error={form.errors.sensitivity} />
                    </Field>
                    <Field label="Status" required>
                        <Select
                            value={form.data.status}
                            onChange={(v) => form.setData('status', v)}
                            options={LLM_STATUS_OPTIONS}
                        />
                        <FieldError error={form.errors.status} />
                    </Field>
                </div>
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

    const setStatus = (llm: Llm, status: string) => {
        if (team) {
            router.put(
                updateLlm([team.slug, llm.id]).url,
                { status },
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
                                        <Badge
                                            tone={
                                                l.status === 'Approved'
                                                    ? 'teal'
                                                    : l.status === 'Deprecated'
                                                      ? 'amber'
                                                      : 'red'
                                            }
                                            dot
                                        >
                                            {l.status}
                                        </Badge>
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
                                            <Toggle
                                                on={l.status === 'Approved'}
                                                onChange={(on) =>
                                                    setStatus(
                                                        l,
                                                        on
                                                            ? 'approved'
                                                            : 'deprecated',
                                                    )
                                                }
                                                size="sm"
                                            />
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
                    open={showAdd}
                    onClose={() => setShowAdd(false)}
                />
                {editing && (
                    <LlmFormModal
                        key={editing.id}
                        llm={editing}
                        open
                        onClose={() => setEditing(null)}
                    />
                )}
            </div>
        </>
    );
}
