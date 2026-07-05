/* ============================================================
   MAACC — Projects
   ============================================================ */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as destroyProject,
    store as storeProject,
    update as updateProject,
} from '@/actions/App/Http/Controllers/Maacc/ProjectController';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    EnvBadge,
    Field,
    Input,
    Modal,
    PageHeader,
    Select,
    Textarea,
    AppMark,
} from '@/components/maacc/ui';
import { inputStyle } from '@/components/maacc/ui';
import type { Project } from '@/maacc/data';
import {
    ChipMultiSelect,
    ENV_OPTIONS,
    FieldError,
    PROJECT_STATUS_OPTIONS,
    toEnumValue,
    useCurrentTeam,
} from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';

interface OwnerProps {
    label: string;
    name: string;
}

function Owner({ label, name }: OwnerProps) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <Avatar name={name} size={24} />
            <div style={{ lineHeight: 1.2 }}>
                <div
                    style={{
                        fontSize: 9.5,
                        color: 'var(--text-3)',
                        fontWeight: 600,
                        textTransform: 'uppercase',
                        letterSpacing: 0.3,
                    }}
                >
                    {label}
                </div>
                <div style={{ fontSize: 12, fontWeight: 600 }}>{name}</div>
            </div>
        </div>
    );
}

function ProjectFormModal({
    project,
    open,
    onClose,
}: {
    project?: Project;
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const MAACC = useMaaccData();
    const isEdit = !!project;
    const llmOptions = MAACC.llms.map((l) => ({
        value: l.uuid ?? l.id,
        label: l.name,
    }));
    const initialLlms = project
        ? project.llms
              .map((slug) => MAACC.llmById(slug)?.uuid)
              .filter((id): id is string => Boolean(id))
        : [];

    const form = useForm<{
        name: string;
        application_id: string;
        environment: string;
        status: string;
        description: string;
        business_owner: string;
        technical_owner: string;
        llm_provider_ids: string[];
    }>({
        name: project?.name ?? '',
        application_id: project ? '' : (MAACC.apps[0]?.uuid ?? ''),
        environment: project ? toEnumValue(project.env) : 'production',
        status: project ? toEnumValue(project.status) : 'active',
        description: project?.desc ?? '',
        business_owner: project?.bizOwner ?? '',
        technical_owner: project?.techOwner ?? '',
        llm_provider_ids: initialLlms,
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const toggleLlm = (value: string) => {
        form.setData(
            'llm_provider_ids',
            form.data.llm_provider_ids.includes(value)
                ? form.data.llm_provider_ids.filter((id) => id !== value)
                : [...form.data.llm_provider_ids, value],
        );
    };

    const submit = () => {
        if (!team) {
            return;
        }

        if (project) {
            form.put(updateProject([team.slug, project.id]).url, {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });

            return;
        }

        form.post(storeProject([team.slug]).url, {
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
            icon="projects"
            title={isEdit ? 'Edit project' : 'Create Project'}
            sub={
                isEdit
                    ? project.name
                    : 'Group agents and tools under an application.'
            }
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
                        {isEdit ? 'Save changes' : 'Create Project'}
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Project name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. Fleet Operations Intelligence"
                    />
                    <FieldError error={form.errors.name} />
                </Field>
                <div style={half}>
                    {!isEdit && (
                        <Field label="Owning application" required>
                            <Select
                                value={form.data.application_id}
                                onChange={(v) =>
                                    form.setData('application_id', v)
                                }
                                options={MAACC.apps.map((a) => ({
                                    value: a.uuid ?? a.id,
                                    label: a.name,
                                }))}
                            />
                            <FieldError error={form.errors.application_id} />
                        </Field>
                    )}
                    <Field label="Environment" required>
                        <Select
                            value={form.data.environment}
                            onChange={(v) => form.setData('environment', v)}
                            options={ENV_OPTIONS}
                        />
                        <FieldError error={form.errors.environment} />
                    </Field>
                    {isEdit && (
                        <Field label="Status" required>
                            <Select
                                value={form.data.status}
                                onChange={(v) => form.setData('status', v)}
                                options={PROJECT_STATUS_OPTIONS}
                            />
                            <FieldError error={form.errors.status} />
                        </Field>
                    )}
                </div>
                <div style={half}>
                    <Field label="Business owner">
                        <Input
                            value={form.data.business_owner}
                            onChange={(e) =>
                                form.setData('business_owner', e.target.value)
                            }
                            placeholder="name@milaha.com"
                        />
                        <FieldError error={form.errors.business_owner} />
                    </Field>
                    <Field label="Technical owner">
                        <Input
                            value={form.data.technical_owner}
                            onChange={(e) =>
                                form.setData('technical_owner', e.target.value)
                            }
                            placeholder="name@milaha.com"
                        />
                        <FieldError error={form.errors.technical_owner} />
                    </Field>
                </div>
                <Field label="Description">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What is this project for?"
                    />
                    <FieldError error={form.errors.description} />
                </Field>
                <Field
                    label="Allowed LLMs"
                    hint="Restrict which approved models agents in this project may use."
                >
                    <ChipMultiSelect
                        options={llmOptions}
                        selected={form.data.llm_provider_ids}
                        onToggle={toggleLlm}
                    />
                    <FieldError error={form.errors.llm_provider_ids} />
                </Field>
            </div>
        </Modal>
    );
}

export default function Projects() {
    const { go, scope } = useMaaccNav();
    const MAACC = useMaaccData();
    const team = useCurrentTeam();
    const [q, setQ] = useState('');
    const [appFilter, setAppFilter] = useState('All applications');
    const [statusFilter, setStatusFilter] = useState('All statuses');
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<Project | null>(null);

    const archive = (project: Project) => {
        if (
            team &&
            window.confirm(`Archive ${project.name}? Its agents will remain.`)
        ) {
            router.delete(destroyProject([team.slug, project.id]).url, {
                preserveScroll: true,
            });
        }
    };

    const appOpts = ['All applications', ...scope.apps.map((a) => a.name)];
    const list: Project[] = scope.projects.filter((p) => {
        const app = MAACC.appById(p.appId);

        return (
            (appFilter === 'All applications' || app?.name === appFilter) &&
            (statusFilter === 'All statuses' || p.status === statusFilter) &&
            p.name.toLowerCase().includes(q.toLowerCase())
        );
    });

    return (
        <>
            <Head title="Projects" />
            <div className="route-anim">
                <PageHeader
                    title="Projects"
                    sub="Logical containers under applications. Projects group agents, allowed models, and tools for a business domain."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => setShowCreate(true)}
                        >
                            Create Project
                        </Btn>
                    }
                />

                <div
                    style={{
                        display: 'flex',
                        gap: 9,
                        marginBottom: 14,
                        flexWrap: 'wrap',
                    }}
                >
                    <div style={{ position: 'relative', width: 260 }}>
                        <Icon
                            name="search"
                            size={15}
                            style={{
                                position: 'absolute',
                                left: 11,
                                top: '50%',
                                transform: 'translateY(-50%)',
                                color: 'var(--text-3)',
                            }}
                        />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search projects…"
                            className="maacc-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        value={appFilter}
                        onChange={setAppFilter}
                        options={appOpts}
                        style={{ width: 210 }}
                    />
                    <Select
                        value={statusFilter}
                        onChange={setStatusFilter}
                        options={['All statuses', 'Active', 'Archived']}
                        style={{ width: 150 }}
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(400px, 1fr))',
                        gap: 12,
                    }}
                >
                    {list.map((p) => {
                        const app = MAACC.appById(p.appId);

                        return (
                            <Card
                                key={p.id}
                                hover
                                onClick={() => go('agents')}
                                style={{ padding: 0, overflow: 'hidden' }}
                            >
                                <div style={{ padding: '14px 16px 12px' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'flex-start',
                                            gap: 10,
                                        }}
                                    >
                                        <div style={{ minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 14.5,
                                                    fontWeight: 700,
                                                }}
                                            >
                                                {p.name}
                                            </div>
                                            <div
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    go('application', {
                                                        id: p.appId,
                                                    });
                                                }}
                                                className="maacc-link"
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    fontSize: 12,
                                                    color: 'var(--text-3)',
                                                    marginTop: 3,
                                                }}
                                            >
                                                <AppMark
                                                    code={p.appId}
                                                    size={16}
                                                />{' '}
                                                {app?.name}
                                            </div>
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                flexShrink: 0,
                                                alignItems: 'center',
                                            }}
                                        >
                                            <EnvBadge env={p.env} />
                                            <Badge
                                                tone={
                                                    p.status === 'Active'
                                                        ? 'teal'
                                                        : 'neutral'
                                                }
                                                dot
                                            >
                                                {p.status}
                                            </Badge>
                                            <div
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                                style={{
                                                    display: 'flex',
                                                    gap: 4,
                                                }}
                                            >
                                                <Btn
                                                    variant="ghost"
                                                    size="icon"
                                                    icon="edit"
                                                    style={{
                                                        height: 28,
                                                        width: 28,
                                                    }}
                                                    onClick={() =>
                                                        setEditing(p)
                                                    }
                                                />
                                                <Btn
                                                    variant="ghost"
                                                    size="icon"
                                                    icon="archive"
                                                    style={{
                                                        height: 28,
                                                        width: 28,
                                                    }}
                                                    onClick={() => archive(p)}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: 'var(--text-2)',
                                            marginTop: 9,
                                            lineHeight: 1.5,
                                        }}
                                    >
                                        {p.desc}
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 18,
                                        padding: '0 16px 12px',
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <Owner
                                        label="Business owner"
                                        name={p.bizOwner}
                                    />
                                    <Owner
                                        label="Technical owner"
                                        name={p.techOwner}
                                    />
                                </div>
                                <div
                                    style={{
                                        padding: '10px 16px',
                                        borderTop: '1px solid var(--border)',
                                        background: 'var(--surface-2)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 5,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        {p.llms.map((l) => (
                                            <Badge key={l} tone="purple" soft>
                                                {MAACC.llmById(l)?.name}
                                            </Badge>
                                        ))}
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 14,
                                            fontSize: 11.5,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        <span>
                                            <b
                                                className="tnum"
                                                style={{ color: 'var(--text)' }}
                                            >
                                                {p.agents}
                                            </b>{' '}
                                            agents
                                        </span>
                                        <span>
                                            <b
                                                className="tnum"
                                                style={{ color: 'var(--text)' }}
                                            >
                                                {p.tools}
                                            </b>{' '}
                                            tools
                                        </span>
                                        <span>
                                            <b
                                                className="tnum"
                                                style={{ color: 'var(--text)' }}
                                            >
                                                {p.runs7d.toLocaleString()}
                                            </b>{' '}
                                            runs/7d
                                        </span>
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <ProjectFormModal
                    open={showCreate}
                    onClose={() => setShowCreate(false)}
                />
                {editing && (
                    <ProjectFormModal
                        key={editing.id}
                        project={editing}
                        open
                        onClose={() => setEditing(null)}
                    />
                )}
            </div>
        </>
    );
}
