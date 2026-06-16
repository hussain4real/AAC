/* ============================================================
   MAAC — Projects
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
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
} from '@/components/maac/ui';
import { inputStyle } from '@/components/maac/ui';
import type { Project } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

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

export default function Projects() {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const [q, setQ] = useState('');
    const [appFilter, setAppFilter] = useState('All applications');
    const [statusFilter, setStatusFilter] = useState('All statuses');
    const [showCreate, setShowCreate] = useState(false);

    const appOpts = ['All applications', ...scope.apps.map((a) => a.name)];
    const list: Project[] = scope.projects.filter((p) => {
        const app = MAAC.appById(p.appId);

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
                            className="maac-input"
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
                        const app = MAAC.appById(p.appId);

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
                                                className="maac-link"
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
                                                {MAAC.llmById(l)?.name}
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

                <Modal
                    open={showCreate}
                    onClose={() => setShowCreate(false)}
                    icon="projects"
                    title="Create Project"
                    sub="Group agents and tools under an application."
                    footer={
                        <>
                            <Btn
                                variant="ghost"
                                onClick={() => setShowCreate(false)}
                            >
                                Cancel
                            </Btn>
                            <Btn
                                variant="primary"
                                icon="check"
                                onClick={() => setShowCreate(false)}
                            >
                                Create Project
                            </Btn>
                        </>
                    }
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <Field label="Project name" required>
                            <Input placeholder="e.g. Fleet Operations Intelligence" />
                        </Field>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Field label="Owning application" required>
                                <Select
                                    value={MAAC.apps[0].name}
                                    onChange={() => {}}
                                    options={MAAC.apps.map((a) => a.name)}
                                />
                            </Field>
                            <Field label="Environment" required>
                                <Select
                                    value="Production"
                                    onChange={() => {}}
                                    options={[
                                        'Production',
                                        'Staging',
                                        'Development',
                                    ]}
                                />
                            </Field>
                        </div>
                        <Field label="Description">
                            <Textarea
                                rows={2}
                                placeholder="What is this project for?"
                            />
                        </Field>
                        <Field
                            label="Allowed LLMs"
                            hint="Restrict which approved models agents in this project may use."
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    flexWrap: 'wrap',
                                }}
                            >
                                {MAAC.llms
                                    .filter((l) => l.status === 'Approved')
                                    .map((l, i) => (
                                        <Badge
                                            key={l.id}
                                            tone={i < 2 ? 'purple' : 'neutral'}
                                            soft
                                        >
                                            {l.name}
                                        </Badge>
                                    ))}
                            </div>
                        </Field>
                    </div>
                </Modal>
            </div>
        </>
    );
}
