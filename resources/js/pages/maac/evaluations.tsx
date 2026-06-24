/* ============================================================
   MAAC — Evaluation Lab (Phase 6F)
   Build golden datasets (no-tool / client-tool / remote-tool /
   connector / RAG cases), run them against an agent through the
   real runtime, inspect per-case checks + citations, and compare
   outcomes across agent versions. A required evaluation gates the
   agent's publication. Every action is wired to the tested console
   endpoints via Wayfinder.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyCase,
    store as storeCase,
} from '@/actions/App/Http/Controllers/Maac/EvaluationCaseController';
import {
    destroy as destroyEvaluation,
    store as runEvaluation,
} from '@/actions/App/Http/Controllers/Maac/EvaluationController';
import {
    destroy as destroyDataset,
    store as storeDataset,
    update as updateDataset,
} from '@/actions/App/Http/Controllers/Maac/EvaluationDatasetController';
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
    Tabs,
    Td,
    Textarea,
    Toggle,
    Tr,
} from '@/components/maac/ui';
import type { Tone } from '@/components/maac/ui';
import {
    ENV_OPTIONS,
    EVALUATION_CASE_KIND_OPTIONS,
    FieldError,
} from '@/maac/forms';
import { Icon } from '@/maac/icons';
import { useMaacData } from '@/maac/use-data';
import type { MaacEvaluation, MaacEvaluationDataset } from '@/types/global';

const NO_PROJECT = 'none';
const NONE_EVAL = 'none';

function splitLines(value: string): string[] {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

/* ---------- Dataset create/edit ---------- */
function DatasetFormModal({
    dataset,
    open,
    onClose,
}: {
    dataset?: MaacEvaluationDataset;
    open: boolean;
    onClose: () => void;
}) {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const isEdit = !!dataset;
    const projectOptions = [
        { value: NO_PROJECT, label: 'Team-wide (no project)' },
        ...MAAC.projects
            .filter((p) => !!p.uuid)
            .map((p) => ({ value: p.uuid as string, label: p.name })),
    ];

    const form = useForm<{
        name: string;
        project_id: string;
        description: string;
    }>({
        name: dataset?.name ?? '',
        project_id: dataset?.projectId ?? NO_PROJECT,
        description: dataset?.description ?? '',
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
            project_id: data.project_id === NO_PROJECT ? null : data.project_id,
        }));

        if (dataset) {
            form.put(updateDataset([currentTeam.slug, dataset.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeDataset([currentTeam.slug]).url, {
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
            icon="flask"
            title={isEdit ? 'Edit dataset' : 'New golden dataset'}
            sub="A reusable set of cases that exercise an agent's behavior before promotion."
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
                        {isEdit ? 'Save changes' : 'Create dataset'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Dataset name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Operations agent — regression suite"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <Field label="Project">
                    <Select
                        value={form.data.project_id}
                        onChange={(v) => form.setData('project_id', v)}
                        options={projectOptions}
                    />
                    <FieldError error={form.errors.project_id} />
                </Field>
                <Field label="Description">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What does this dataset validate?"
                    />
                    <FieldError error={form.errors.description} />
                </Field>
            </div>
        </Modal>
    );
}

/* ---------- Case create ---------- */
function CaseFormModal({
    dataset,
    open,
    onClose,
}: {
    dataset: MaacEvaluationDataset;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const [stubError, setStubError] = useState<string | null>(null);
    const form = useForm<{
        name: string;
        kind: string;
        input: string;
        expected_contains: string;
        expected_tool: string;
        forbidden_phrases: string;
        expects_citation: boolean;
        max_cost: string;
        max_latency_ms: string;
        tool_stubs: string;
    }>({
        name: '',
        kind: 'no_tool',
        input: '',
        expected_contains: '',
        expected_tool: '',
        forbidden_phrases: '',
        expects_citation: false,
        max_cost: '',
        max_latency_ms: '',
        tool_stubs: '',
    });

    const close = () => {
        form.clearErrors();
        setStubError(null);
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        let parsedStubs: Record<string, unknown> | null = null;

        if (form.data.kind === 'client_tool' && form.data.tool_stubs.trim()) {
            try {
                parsedStubs = JSON.parse(form.data.tool_stubs);
            } catch {
                setStubError('Tool stubs must be valid JSON.');

                return;
            }
        }

        setStubError(null);

        form.transform((data) => ({
            evaluation_dataset_id: dataset.uuid,
            name: data.name,
            kind: data.kind,
            input: data.input,
            expectations: {
                expected_contains: splitLines(data.expected_contains),
                expected_tool: data.expected_tool || null,
                forbidden_phrases: splitLines(data.forbidden_phrases),
                expects_citation: data.expects_citation,
                max_cost: data.max_cost === '' ? null : Number(data.max_cost),
                max_latency_ms:
                    data.max_latency_ms === ''
                        ? null
                        : Number(data.max_latency_ms),
            },
            tool_stubs: parsedStubs,
        }));

        form.post(storeCase([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const showsTool = form.data.kind !== 'no_tool';

    return (
        <Modal
            open={open}
            onClose={close}
            icon="flask"
            title="Add evaluation case"
            sub={`A case for ${dataset.name}.`}
            width={620}
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
                        Add case
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Case name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Summarizes today's vessels"
                        />
                        <FieldError error={form.errors.name} />
                    </Field>
                    <Field label="Workflow" required>
                        <Select
                            value={form.data.kind}
                            onChange={(v) => form.setData('kind', v)}
                            options={EVALUATION_CASE_KIND_OPTIONS}
                        />
                    </Field>
                </div>
                <Field label="Input prompt" required>
                    <Textarea
                        rows={2}
                        value={form.data.input}
                        onChange={(e) => form.setData('input', e.target.value)}
                        placeholder="Summarize today's vessel schedule."
                    />
                    <FieldError error={form.errors.input} />
                </Field>
                <Field
                    label="Answer must contain"
                    hint="One phrase per line (correctness check)."
                >
                    <Textarea
                        rows={2}
                        value={form.data.expected_contains}
                        onChange={(e) =>
                            form.setData('expected_contains', e.target.value)
                        }
                        placeholder="on schedule"
                    />
                </Field>
                <Field
                    label="Answer must NOT contain"
                    hint="One phrase per line (safety check)."
                >
                    <Textarea
                        rows={2}
                        value={form.data.forbidden_phrases}
                        onChange={(e) =>
                            form.setData('forbidden_phrases', e.target.value)
                        }
                        placeholder="password"
                    />
                </Field>
                {showsTool && (
                    <Field
                        label="Expected tool"
                        hint="The tool slug the run must call."
                    >
                        <Input
                            value={form.data.expected_tool}
                            onChange={(e) =>
                                form.setData('expected_tool', e.target.value)
                            }
                            placeholder="getRecords"
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                    </Field>
                )}
                {form.data.kind === 'client_tool' && (
                    <Field
                        label="Client tool stub (JSON)"
                        hint='The result the evaluation feeds back, e.g. {"getRecords": {"records": [], "total": 0}}'
                    >
                        <Textarea
                            rows={3}
                            value={form.data.tool_stubs}
                            onChange={(e) =>
                                form.setData('tool_stubs', e.target.value)
                            }
                            placeholder='{"getRecords": {"records": ["a"], "total": 1}}'
                            style={{ fontFamily: 'var(--mono)' }}
                        />
                        <FieldError error={stubError ?? undefined} />
                    </Field>
                )}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Max cost ($)">
                        <Input
                            type="number"
                            value={form.data.max_cost}
                            onChange={(e) =>
                                form.setData('max_cost', e.target.value)
                            }
                            placeholder="optional"
                        />
                    </Field>
                    <Field label="Max latency (ms)">
                        <Input
                            type="number"
                            value={form.data.max_latency_ms}
                            onChange={(e) =>
                                form.setData('max_latency_ms', e.target.value)
                            }
                            placeholder="optional"
                        />
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
                            Requires a citation
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            The run must surface at least one knowledge
                            citation.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.expects_citation}
                        onChange={(v) => form.setData('expects_citation', v)}
                    />
                </div>
            </div>
        </Modal>
    );
}

/* ---------- Run an evaluation ---------- */
function RunEvaluationModal({
    dataset,
    open,
    onClose,
}: {
    dataset: MaacEvaluationDataset;
    open: boolean;
    onClose: () => void;
}) {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const agentOptions = MAAC.agents
        .filter((a) => !!a.uuid)
        .map((a) => ({ value: a.uuid as string, label: a.name }));

    const form = useForm<{
        evaluation_dataset_id: string;
        agent_id: string;
        environment: string;
        is_required: boolean;
    }>({
        evaluation_dataset_id: dataset.uuid,
        agent_id: agentOptions[0]?.value ?? '',
        environment: 'production',
        is_required: false,
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.post(runEvaluation([currentTeam.slug]).url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="play"
            title="Run evaluation"
            sub={`Run ${dataset.name} against an agent.`}
            width={520}
            footer={
                <>
                    <Btn variant="ghost" onClick={close}>
                        Cancel
                    </Btn>
                    <Btn
                        variant="primary"
                        icon="play"
                        disabled={form.processing || agentOptions.length === 0}
                        onClick={submit}
                    >
                        Run evaluation
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Agent" required>
                    <Select
                        value={form.data.agent_id}
                        onChange={(v) => form.setData('agent_id', v)}
                        options={agentOptions}
                    />
                    <FieldError error={form.errors.agent_id} />
                </Field>
                <Field label="Environment" required>
                    <Select
                        value={form.data.environment}
                        onChange={(v) => form.setData('environment', v)}
                        options={ENV_OPTIONS}
                    />
                    <FieldError error={form.errors.environment} />
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
                            Gate promotion on this result
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            The agent cannot be published while this required
                            evaluation has not passed.
                        </div>
                    </div>
                    <Toggle
                        on={form.data.is_required}
                        onChange={(v) => form.setData('is_required', v)}
                    />
                </div>
            </div>
        </Modal>
    );
}

/* ---------- Datasets tab ---------- */
function DatasetsTab() {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const datasets = MAAC.evaluationDatasets;
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<MaacEvaluationDataset | undefined>();
    const [caseOpen, setCaseOpen] = useState(false);
    const [runOpen, setRunOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const selected = datasets.find((d) => d.id === selectedId) ?? null;

    const removeDataset = (dataset: MaacEvaluationDataset) => {
        if (window.confirm(`Delete the dataset ${dataset.name}?`)) {
            router.delete(destroyDataset([teamSlug, dataset.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    const removeCase = (id: string, name: string) => {
        if (window.confirm(`Remove the case "${name}"?`)) {
            router.delete(destroyCase([teamSlug, id]).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    marginTop: 16,
                }}
            >
                <Btn
                    variant="primary"
                    icon="plus"
                    onClick={() => {
                        setEditing(undefined);
                        setFormOpen(true);
                    }}
                >
                    New dataset
                </Btn>
            </div>

            {datasets.length === 0 ? (
                <Card style={{ marginTop: 12 }}>
                    <EmptyState
                        icon="flask"
                        title="No golden datasets yet"
                        desc="Create a dataset and add cases to test an agent before promotion."
                    />
                </Card>
            ) : (
                <Table
                    style={{ marginTop: 12 }}
                    columns={[
                        { label: 'Dataset' },
                        { label: 'Project' },
                        { label: 'Cases', align: 'center' },
                        { label: '', align: 'right' },
                    ]}
                >
                    {datasets.map((d) => (
                        <Tr key={d.id} onClick={() => setSelectedId(d.id)}>
                            <Td strong>{d.name}</Td>
                            <Td>{d.project ?? 'Team-wide'}</Td>
                            <Td align="center">{d.caseCount ?? 0}</Td>
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
                                        icon="edit"
                                        title="Edit"
                                        onClick={() => {
                                            setEditing(d);
                                            setFormOpen(true);
                                        }}
                                    />
                                    <IconBtn
                                        icon="trash"
                                        title="Delete"
                                        danger
                                        onClick={() => removeDataset(d)}
                                    />
                                </div>
                            </Td>
                        </Tr>
                    ))}
                </Table>
            )}

            {selected && (
                <Card style={{ marginTop: 16 }} pad={false}>
                    <SectionHeader
                        title={`Cases · ${selected.name}`}
                        sub={`${selected.cases.length} case(s) across the runtime surface.`}
                        icon="flask"
                        style={{ padding: '14px 16px 0' }}
                        right={
                            <div style={{ display: 'flex', gap: 8 }}>
                                <Btn
                                    variant="soft"
                                    icon="plus"
                                    onClick={() => setCaseOpen(true)}
                                >
                                    Add case
                                </Btn>
                                <Btn
                                    variant="primary"
                                    icon="play"
                                    disabled={selected.cases.length === 0}
                                    onClick={() => setRunOpen(true)}
                                >
                                    Run evaluation
                                </Btn>
                            </div>
                        }
                    />
                    {selected.cases.length === 0 ? (
                        <EmptyState
                            icon="flask"
                            title="No cases yet"
                            desc="Add a case to exercise a no-tool, client-tool, remote, connector, or RAG workflow."
                        />
                    ) : (
                        <div style={{ padding: 14 }}>
                            <Table
                                columns={[
                                    { label: 'Case' },
                                    { label: 'Workflow' },
                                    { label: 'Input' },
                                    { label: '', align: 'right' },
                                ]}
                            >
                                {selected.cases.map((c) => (
                                    <Tr key={c.id}>
                                        <Td strong>{c.name}</Td>
                                        <Td>
                                            <Badge tone="blue" soft>
                                                {c.kindLabel}
                                            </Badge>
                                        </Td>
                                        <Td>
                                            <span
                                                style={{
                                                    color: 'var(--text-3)',
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                {c.input.length > 60
                                                    ? c.input.slice(0, 60) + '…'
                                                    : c.input}
                                            </span>
                                        </Td>
                                        <Td align="right">
                                            <IconBtn
                                                icon="trash"
                                                title="Remove case"
                                                danger
                                                onClick={() =>
                                                    removeCase(c.id, c.name)
                                                }
                                            />
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                        </div>
                    )}
                </Card>
            )}

            <DatasetFormModal
                dataset={editing}
                open={formOpen}
                onClose={() => setFormOpen(false)}
            />
            {selected && (
                <>
                    <CaseFormModal
                        dataset={selected}
                        open={caseOpen}
                        onClose={() => setCaseOpen(false)}
                    />
                    <RunEvaluationModal
                        dataset={selected}
                        open={runOpen}
                        onClose={() => setRunOpen(false)}
                    />
                </>
            )}
        </>
    );
}

/* ---------- Runs tab ---------- */
function RunsTab() {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const evaluations = MAAC.evaluations;
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const selected = evaluations.find((e) => e.id === selectedId) ?? null;

    const remove = (evaluation: MaacEvaluation) => {
        if (window.confirm(`Delete evaluation "${evaluation.label}"?`)) {
            router.delete(destroyEvaluation([teamSlug, evaluation.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    if (evaluations.length === 0) {
        return (
            <Card style={{ marginTop: 16 }}>
                <EmptyState
                    icon="flask"
                    title="No evaluations run yet"
                    desc="Run a golden dataset against an agent from the Datasets tab."
                />
            </Card>
        );
    }

    return (
        <>
            <Table
                style={{ marginTop: 16 }}
                columns={[
                    { label: 'Evaluation' },
                    { label: 'Agent' },
                    { label: 'Version' },
                    { label: 'Pass rate', align: 'center' },
                    { label: 'Status', align: 'center' },
                    { label: 'Gate', align: 'center' },
                    { label: '', align: 'right' },
                ]}
            >
                {evaluations.map((e) => (
                    <Tr key={e.id} onClick={() => setSelectedId(e.id)}>
                        <Td strong>{e.label}</Td>
                        <Td>{e.agentName ?? '—'}</Td>
                        <Td mono>{e.agentVersion}</Td>
                        <Td align="center">
                            {e.casesPassed}/{e.casesTotal} ({e.passRate}%)
                        </Td>
                        <Td align="center">
                            <Badge
                                tone={
                                    e.status === 'passed'
                                        ? 'teal'
                                        : e.status === 'failed'
                                          ? 'red'
                                          : 'neutral'
                                }
                                soft
                                dot
                            >
                                {e.statusLabel}
                            </Badge>
                        </Td>
                        <Td align="center">
                            {e.isRequired ? (
                                <Badge tone="purple" soft>
                                    Required
                                </Badge>
                            ) : (
                                <span style={{ color: 'var(--text-3)' }}>
                                    —
                                </span>
                            )}
                        </Td>
                        <Td align="right">
                            <div
                                onClick={(ev) => ev.stopPropagation()}
                                style={{
                                    display: 'inline-flex',
                                    justifyContent: 'flex-end',
                                }}
                            >
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

            {selected && <ResultsPanel evaluation={selected} />}
        </>
    );
}

function ResultsPanel({ evaluation }: { evaluation: MaacEvaluation }) {
    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title={`Results · ${evaluation.label}`}
                sub={`${evaluation.casesPassed}/${evaluation.casesTotal} passed · correctness ${evaluation.correctnessRate}% · safety ${evaluation.safetyRate}% · citation ${evaluation.citationRate}% · $${evaluation.totalCost} · ${evaluation.avgLatencyMs}ms avg`}
                icon="flask"
                style={{ padding: '14px 16px 0' }}
            />
            <div
                style={{
                    padding: 14,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 10,
                }}
            >
                {evaluation.results.map((r) => (
                    <div
                        key={r.id}
                        style={{
                            border: '1px solid var(--border-2)',
                            borderRadius: 'var(--r-sm)',
                            padding: 12,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 8,
                            }}
                        >
                            <Badge tone={r.passed ? 'teal' : 'red'} soft dot>
                                {r.passed ? 'Pass' : 'Fail'}
                            </Badge>
                            <span style={{ fontWeight: 600, fontSize: 13 }}>
                                {r.caseName}
                            </span>
                            <Badge tone="blue" soft>
                                {r.kindLabel}
                            </Badge>
                            {r.failureReason && (
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--red-600)',
                                        fontFamily: 'var(--mono)',
                                    }}
                                >
                                    {r.failureReason}
                                </span>
                            )}
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 6,
                                flexWrap: 'wrap',
                                marginBottom: r.citations.length ? 8 : 0,
                            }}
                        >
                            {r.checks.map((check, i) => (
                                <span
                                    key={i}
                                    title={check.detail}
                                    style={{ display: 'inline-flex' }}
                                >
                                    <Badge
                                        tone={check.passed ? 'teal' : 'red'}
                                        soft
                                    >
                                        {check.passed ? '✓' : '✕'} {check.type}
                                    </Badge>
                                </span>
                            ))}
                        </div>
                        {r.citations.length > 0 && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--text-3)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                }}
                            >
                                <Icon name="book" size={12} />
                                {r.citations.length} citation(s):{' '}
                                {r.citations
                                    .map(
                                        (c) =>
                                            (c.document as string) ?? 'source',
                                    )
                                    .join(', ')}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </Card>
    );
}

/* ---------- Compare tab ---------- */
function CompareTab() {
    const MAAC = useMaacData();
    const evaluations = MAAC.evaluations;
    const [baseId, setBaseId] = useState(NONE_EVAL);
    const [candId, setCandId] = useState(NONE_EVAL);

    const options = [
        { value: NONE_EVAL, label: 'Select an evaluation…' },
        ...evaluations.map((e) => ({
            value: e.id,
            label: `${e.label} (${e.agentVersion})`,
        })),
    ];

    const base = evaluations.find((e) => e.id === baseId) ?? null;
    const cand = evaluations.find((e) => e.id === candId) ?? null;

    return (
        <div style={{ marginTop: 16 }}>
            <Card>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Baseline">
                        <Select
                            value={baseId}
                            onChange={setBaseId}
                            options={options}
                        />
                    </Field>
                    <Field label="Candidate">
                        <Select
                            value={candId}
                            onChange={setCandId}
                            options={options}
                        />
                    </Field>
                </div>
            </Card>

            {base && cand ? (
                <Card style={{ marginTop: 16 }} pad={false}>
                    <SectionHeader
                        title="Comparison"
                        sub={`${base.label} → ${cand.label}`}
                        icon="flow"
                        style={{ padding: '14px 16px 0' }}
                    />
                    <div style={{ padding: 14 }}>
                        <Table
                            columns={[
                                { label: 'Metric' },
                                { label: 'Baseline', align: 'center' },
                                { label: 'Candidate', align: 'center' },
                                { label: 'Δ', align: 'center' },
                            ]}
                        >
                            <CompareRow
                                label="Pass rate"
                                base={base.passRate}
                                cand={cand.passRate}
                                suffix="%"
                                higherBetter
                            />
                            <CompareRow
                                label="Correctness"
                                base={base.correctnessRate}
                                cand={cand.correctnessRate}
                                suffix="%"
                                higherBetter
                            />
                            <CompareRow
                                label="Safety"
                                base={base.safetyRate}
                                cand={cand.safetyRate}
                                suffix="%"
                                higherBetter
                            />
                            <CompareRow
                                label="Citation"
                                base={base.citationRate}
                                cand={cand.citationRate}
                                suffix="%"
                                higherBetter
                            />
                            <CompareRow
                                label="Total cost ($)"
                                base={base.totalCost}
                                cand={cand.totalCost}
                                higherBetter={false}
                            />
                            <CompareRow
                                label="Avg latency (ms)"
                                base={base.avgLatencyMs}
                                cand={cand.avgLatencyMs}
                                higherBetter={false}
                            />
                        </Table>
                        <div
                            style={{
                                marginTop: 12,
                                display: 'flex',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <ChangeBadge
                                label="Agent version"
                                from={base.agentVersion}
                                to={cand.agentVersion}
                            />
                            <ChangeBadge
                                label="Model"
                                from={base.modelCode ?? '—'}
                                to={cand.modelCode ?? '—'}
                            />
                            <ChangeBadge
                                label="Prompt"
                                from={base.promptFingerprint ?? '—'}
                                to={cand.promptFingerprint ?? '—'}
                            />
                        </div>
                    </div>
                </Card>
            ) : (
                <Card style={{ marginTop: 16 }}>
                    <EmptyState
                        icon="flow"
                        title="Pick two evaluations to compare"
                        desc="Compare pass rate, correctness, safety, citation, cost, and latency across agent versions."
                    />
                </Card>
            )}
        </div>
    );
}

function CompareRow({
    label,
    base,
    cand,
    suffix = '',
    higherBetter,
}: {
    label: string;
    base: number;
    cand: number;
    suffix?: string;
    higherBetter: boolean;
}) {
    const delta = Math.round((cand - base) * 100) / 100;
    const improved = higherBetter ? delta > 0 : delta < 0;
    const worse = higherBetter ? delta < 0 : delta > 0;
    const tone: Tone = improved ? 'teal' : worse ? 'red' : 'neutral';

    return (
        <Tr>
            <Td strong>{label}</Td>
            <Td align="center">
                {base}
                {suffix}
            </Td>
            <Td align="center">
                {cand}
                {suffix}
            </Td>
            <Td align="center">
                <Badge tone={tone} soft>
                    {delta > 0 ? '+' : ''}
                    {delta}
                    {suffix}
                </Badge>
            </Td>
        </Tr>
    );
}

function ChangeBadge({
    label,
    from,
    to,
}: {
    label: string;
    from: string;
    to: string;
}) {
    const changed = from !== to;

    return (
        <Badge tone={changed ? 'amber' : 'neutral'} soft>
            {label}: {changed ? `${from} → ${to}` : 'unchanged'}
        </Badge>
    );
}

export default function Evaluations() {
    const MAAC = useMaacData();
    const [tab, setTab] = useState('datasets');
    const required = MAAC.evaluations.filter((e) => e.isRequired).length;

    return (
        <>
            <Head title="Evaluation Lab" />
            <div className="route-anim">
                <PageHeader
                    title="Evaluation Lab"
                    sub="Test agent quality, safety, citations, and regressions before production rollout. Run golden datasets against an agent through the real runtime, inspect per-case checks, and gate promotion on a required evaluation."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: 12,
                    }}
                >
                    <Stat
                        label="Datasets"
                        value={MAAC.evaluationDatasets.length}
                        icon="flask"
                    />
                    <Stat
                        label="Evaluations run"
                        value={MAAC.evaluations.length}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Promotion gates"
                        value={required}
                        icon="shield"
                        tone="purple"
                    />
                </div>

                <Tabs
                    active={tab}
                    onChange={setTab}
                    tabs={[
                        {
                            id: 'datasets',
                            label: 'Datasets',
                            icon: 'flask',
                            count: MAAC.evaluationDatasets.length,
                        },
                        {
                            id: 'runs',
                            label: 'Runs',
                            icon: 'runs',
                            count: MAAC.evaluations.length,
                        },
                        { id: 'compare', label: 'Compare', icon: 'flow' },
                    ]}
                />

                {tab === 'datasets' && <DatasetsTab />}
                {tab === 'runs' && <RunsTab />}
                {tab === 'compare' && <CompareTab />}
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    icon,
    tone = 'orange',
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
