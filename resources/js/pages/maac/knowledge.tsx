/* ============================================================
   MAAC — Knowledge Sources (Phase 6F)
   Register governed knowledge (RAG) sources, ingest documents
   into them (chunked + indexed), and manage their lifecycle.
   A sensitive source is gated behind an ingestion approval before
   the runtime may retrieve from it. Every action is wired to the
   tested console write endpoints via Wayfinder.
   ============================================================ */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyDocument,
    store as storeDocument,
} from '@/actions/App/Http/Controllers/Maac/KnowledgeDocumentController';
import {
    destroy as destroySource,
    reindex as reindexSource,
    store as storeSource,
    update as updateSource,
} from '@/actions/App/Http/Controllers/Maac/KnowledgeSourceController';
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
    SensBadge,
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
    SENSITIVITY_OPTIONS,
} from '@/maac/forms';
import { Icon } from '@/maac/icons';
import { useMaacData } from '@/maac/use-data';
import type { MaacKnowledgeSource } from '@/types/global';

const NO_APP = 'none';

function SourceFormModal({
    source,
    open,
    onClose,
}: {
    source?: MaacKnowledgeSource;
    open: boolean;
    onClose: () => void;
}) {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const isEdit = !!source;
    const appOptions = [
        { value: NO_APP, label: 'Platform (all applications)' },
        ...MAAC.apps
            .filter((a) => !!a.uuid)
            .map((a) => ({ value: a.uuid as string, label: a.name })),
    ];

    const form = useForm<{
        name: string;
        application_id: string;
        description: string;
        sensitivity: string;
        requires_approval: boolean;
        environments: string[];
    }>({
        name: source?.name ?? '',
        application_id: NO_APP,
        description: source?.description ?? '',
        sensitivity: (source?.sensitivity ?? 'Internal').toLowerCase(),
        requires_approval: source?.requiresApproval ?? false,
        environments: source
            ? source.environments.map((e) => e.toLowerCase())
            : ['production'],
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

    return (
        <Modal
            open={open}
            onClose={close}
            icon="book"
            title={
                isEdit ? 'Edit knowledge source' : 'Register knowledge source'
            }
            sub="MAAC indexes and retrieves from this approved document collection on behalf of a knowledge-retrieval tool."
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
                        {isEdit ? 'Save changes' : 'Register source'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Source name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Vessel Operations Handbook"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Owner">
                        <Select
                            value={form.data.application_id}
                            onChange={(v) => form.setData('application_id', v)}
                            options={appOptions}
                        />
                        <FieldError error={form.errors.application_id} />
                    </Field>
                    <Field
                        label="Data sensitivity"
                        required
                        hint="Confidential or higher requires ingestion approval."
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
                    label="Environments"
                    required
                    hint="Where the runtime may retrieve from this source."
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
                        placeholder="What does this source cover?"
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
                            Require ingestion approval
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            Gate this source behind a governance approval before
                            the runtime may retrieve from it.
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

function DocumentFormModal({
    source,
    open,
    onClose,
}: {
    source: MaacKnowledgeSource;
    open: boolean;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;
    const [mode, setMode] = useState<'paste' | 'upload'>('paste');
    const [fileKey, setFileKey] = useState(0);
    const form = useForm<{
        title: string;
        uri: string;
        body: string;
        document: File | null;
        author: string;
        published_at: string;
    }>({
        title: '',
        uri: '',
        body: '',
        document: null,
        author: '',
        published_at: '',
    });

    const reset = () => {
        form.reset();
        setMode('paste');
        setFileKey((key) => key + 1);
    };

    const close = () => {
        form.clearErrors();
        reset();
        onClose();
    };

    const submit = () => {
        if (!currentTeam) {
            return;
        }

        form.transform((data) => ({
            title: data.title,
            uri: data.uri || null,
            body: mode === 'paste' ? data.body : '',
            document: mode === 'upload' ? data.document : null,
            metadata: {
                author: data.author || null,
                published_at: data.published_at || null,
            },
        }));

        form.post(storeDocument([currentTeam.slug, source.id]).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={close}
            icon="doc"
            title="Ingest document"
            sub={`Add a document to ${source.name}. It is chunked and indexed for retrieval.`}
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
                        Ingest &amp; index
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Document title" required>
                    <Input
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="Berth allocation procedure"
                    />
                    <FieldError error={form.errors.title} />
                </Field>
                <Field label="Source URI" hint="Used for citation attribution.">
                    <Input
                        value={form.data.uri}
                        onChange={(e) => form.setData('uri', e.target.value)}
                        placeholder="https://docs.example.com/berth-allocation"
                        style={{ fontFamily: 'var(--mono)' }}
                    />
                    <FieldError error={form.errors.uri} />
                </Field>
                <div>
                    <div style={{ display: 'flex', gap: 6, marginBottom: 10 }}>
                        <Btn
                            variant={mode === 'paste' ? 'primary' : 'soft'}
                            onClick={() => setMode('paste')}
                        >
                            Paste text
                        </Btn>
                        <Btn
                            variant={mode === 'upload' ? 'primary' : 'soft'}
                            onClick={() => setMode('upload')}
                        >
                            Upload file
                        </Btn>
                    </div>
                    {mode === 'paste' ? (
                        <Field
                            label="Document body"
                            required
                            hint="Plain text. Paragraphs become retrievable chunks."
                        >
                            <Textarea
                                rows={8}
                                value={form.data.body}
                                onChange={(e) =>
                                    form.setData('body', e.target.value)
                                }
                                placeholder="Paste the document content here…"
                            />
                            <FieldError error={form.errors.body} />
                        </Field>
                    ) : (
                        <Field
                            label="Document file"
                            required
                            hint="TXT, Markdown, CSV, PDF, or Word (.docx). The file is stored and indexed from storage."
                        >
                            <input
                                key={fileKey}
                                type="file"
                                accept=".txt,.md,.markdown,.csv,.pdf,.docx"
                                onChange={(e) =>
                                    form.setData(
                                        'document',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                                style={{ fontSize: 13, color: 'var(--text)' }}
                            />
                            <FieldError error={form.errors.document} />
                        </Field>
                    )}
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Author">
                        <Input
                            value={form.data.author}
                            onChange={(e) =>
                                form.setData('author', e.target.value)
                            }
                            placeholder="Operations team"
                        />
                    </Field>
                    <Field label="Published date">
                        <Input
                            value={form.data.published_at}
                            onChange={(e) =>
                                form.setData('published_at', e.target.value)
                            }
                            placeholder="2026-01-01"
                        />
                    </Field>
                </div>
            </div>
        </Modal>
    );
}

function DocumentsPanel({
    source,
    onAdd,
}: {
    source: MaacKnowledgeSource;
    onAdd: () => void;
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const reindex = () => {
        router.post(
            reindexSource([teamSlug, source.id]).url,
            {},
            { preserveScroll: true },
        );
    };

    const removeDocument = (id: string, title: string) => {
        if (window.confirm(`Remove the document "${title}"?`)) {
            router.delete(destroyDocument([teamSlug, id]).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Card style={{ marginTop: 16 }} pad={false}>
            <SectionHeader
                title={`Documents · ${source.name}`}
                sub={
                    source.lastIndexed
                        ? `${source.documentCount} document(s), ${source.chunkCount} indexed chunk(s). Last indexed ${source.lastIndexed}.`
                        : 'Ingest a document to build this source’s index.'
                }
                icon="book"
                style={{ padding: '14px 16px 0' }}
                right={
                    <div style={{ display: 'flex', gap: 8 }}>
                        <Btn variant="soft" icon="refresh" onClick={reindex}>
                            Re-index
                        </Btn>
                        <Btn variant="primary" icon="plus" onClick={onAdd}>
                            Ingest document
                        </Btn>
                    </div>
                }
            />
            {source.documents.length === 0 ? (
                <EmptyState
                    icon="doc"
                    title="No documents yet"
                    desc="Ingest a document to index it for retrieval."
                />
            ) : (
                <div style={{ padding: 14 }}>
                    <Table
                        columns={[
                            { label: 'Title' },
                            { label: 'URI' },
                            { label: 'Chunks', align: 'center' },
                            { label: 'Indexed' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {source.documents.map((doc) => (
                            <Tr key={doc.id}>
                                <Td strong>
                                    {doc.title}
                                    {doc.uploaded && doc.originalFilename ? (
                                        <span
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 4,
                                                marginTop: 2,
                                                fontSize: 11,
                                                fontWeight: 400,
                                                fontFamily: 'var(--mono)',
                                                color: 'var(--text-3)',
                                            }}
                                        >
                                            <Icon name="doc" size={11} />
                                            {doc.originalFilename}
                                        </span>
                                    ) : null}
                                </Td>
                                <Td>
                                    <span
                                        style={{
                                            fontFamily: 'var(--mono)',
                                            fontSize: 12,
                                            wordBreak: 'break-all',
                                        }}
                                    >
                                        {doc.uri ?? '—'}
                                    </span>
                                </Td>
                                <Td align="center">{doc.chunkCount ?? 0}</Td>
                                <Td>{doc.indexedAt ?? '—'}</Td>
                                <Td align="right">
                                    <IconBtn
                                        icon="trash"
                                        title="Remove document"
                                        danger
                                        onClick={() =>
                                            removeDocument(doc.id, doc.title)
                                        }
                                    />
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                </div>
            )}
        </Card>
    );
}

export default function Knowledge() {
    const MAAC = useMaacData();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const sources = MAAC.knowledgeSources;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<MaacKnowledgeSource | undefined>();
    const [docModalOpen, setDocModalOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const selected = sources.find((s) => s.id === selectedId) ?? null;
    const active = sources.filter((s) => s.status === 'active').length;
    const totalDocs = sources.reduce((sum, s) => sum + s.documentCount, 0);

    const openCreate = () => {
        setEditing(undefined);
        setModalOpen(true);
    };

    const toggleStatus = (source: MaacKnowledgeSource) => {
        router.put(
            updateSource([teamSlug, source.id]).url,
            { status: source.status === 'active' ? 'disabled' : 'active' },
            { preserveScroll: true },
        );
    };

    const remove = (source: MaacKnowledgeSource) => {
        if (window.confirm(`Delete the knowledge source ${source.name}?`)) {
            router.delete(destroySource([teamSlug, source.id]).url, {
                preserveScroll: true,
                onSuccess: () => setSelectedId(null),
            });
        }
    };

    return (
        <>
            <Head title="Knowledge Sources" />
            <div className="route-anim">
                <PageHeader
                    title="Knowledge Sources"
                    sub="Register governed document collections for retrieval-augmented agents. MAAC indexes each source and a knowledge-retrieval tool retrieves cited passages from it — with the same schema, sensitivity, approval, and audit standards as every other tool."
                    actions={
                        <Btn variant="primary" icon="plus" onClick={openCreate}>
                            Register source
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
                    <Stat label="Sources" value={sources.length} icon="book" />
                    <Stat
                        label="Active"
                        value={active}
                        icon="check2"
                        tone="teal"
                    />
                    <Stat
                        label="Documents indexed"
                        value={totalDocs}
                        icon="doc"
                        tone="purple"
                    />
                </div>

                {sources.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="book"
                            title="No knowledge sources yet"
                            desc="Register a source and ingest documents to give your agents governed, cited knowledge retrieval."
                            action={
                                <Btn
                                    variant="primary"
                                    icon="plus"
                                    onClick={openCreate}
                                >
                                    Register source
                                </Btn>
                            }
                        />
                    </Card>
                ) : (
                    <Table
                        columns={[
                            { label: 'Source' },
                            { label: 'Sensitivity' },
                            { label: 'Environments' },
                            { label: 'Docs', align: 'center' },
                            { label: 'Tools', align: 'center' },
                            { label: 'Status', align: 'center' },
                            { label: '', align: 'right' },
                        ]}
                    >
                        {sources.map((s) => (
                            <Tr key={s.id} onClick={() => setSelectedId(s.id)}>
                                <Td strong>{s.name}</Td>
                                <Td>
                                    <SensBadge level={s.sensitivity} />
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
                                <Td align="center">{s.documentCount}</Td>
                                <Td align="center">{s.toolCount ?? 0}</Td>
                                <Td align="center">
                                    {s.status === 'draft' ? (
                                        <Badge tone="amber" soft>
                                            Pending approval
                                        </Badge>
                                    ) : (
                                        <div
                                            onClick={(ev) =>
                                                ev.stopPropagation()
                                            }
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
                                    )}
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
                                            icon="edit"
                                            title="Edit"
                                            onClick={() => {
                                                setEditing(s);
                                                setModalOpen(true);
                                            }}
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

                {selected && (
                    <DocumentsPanel
                        source={selected}
                        onAdd={() => setDocModalOpen(true)}
                    />
                )}

                <SourceFormModal
                    source={editing}
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                />
                {selected && (
                    <DocumentFormModal
                        source={selected}
                        open={docModalOpen}
                        onClose={() => setDocModalOpen(false)}
                    />
                )}
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
