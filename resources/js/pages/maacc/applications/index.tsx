/* ============================================================
   MAACC — Applications (list)
   ============================================================ */
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/Maacc/ApplicationController';
import {
    AppMark,
    APP_STATUS,
    Badge,
    Btn,
    Card,
    EmptyState,
    EnvBadge,
    Field,
    Input,
    Modal,
    PageHeader,
    Segmented,
    Select,
    Table,
    Td,
    Textarea,
    Tr,
    inputStyle,
} from '@/components/maacc/ui';
import type { Application } from '@/maacc/data';
import { ENV_OPTIONS, FieldError, useCurrentTeam } from '@/maacc/forms';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';

/* ---- Local sub-components ---- */

function ToolMeter({ req, done }: { req: number; done: number }) {
    const pct = req ? (done / req) * 100 : 100;
    const tone =
        pct === 100
            ? 'var(--teal-500)'
            : pct >= 50
              ? 'var(--orange-600)'
              : 'var(--red-500)';

    return (
        <div
            className="maacc-tip"
            data-tip={`${done} of ${req} client-side tools implemented`}
            style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}
        >
            <div
                style={{
                    width: 46,
                    height: 6,
                    background: 'var(--track)',
                    borderRadius: 999,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${pct}%`,
                        background: tone,
                    }}
                />
            </div>
            <span
                className="tnum mono"
                style={{
                    fontSize: 11.5,
                    fontWeight: 600,
                    color: 'var(--text-2)',
                }}
            >
                {done}/{req}
            </span>
        </div>
    );
}

function Stat3({
    label,
    value,
    border,
}: {
    label: string;
    value: React.ReactNode;
    border?: boolean;
}) {
    return (
        <div
            style={{
                padding: '10px 14px',
                borderLeft: border ? '1px solid var(--border)' : 'none',
            }}
        >
            <div
                style={{
                    fontSize: 10.5,
                    color: 'var(--text-3)',
                    fontWeight: 600,
                    textTransform: 'uppercase',
                    letterSpacing: 0.3,
                }}
            >
                {label}
            </div>
            <div
                className="tnum"
                style={{ fontSize: 15, fontWeight: 700, marginTop: 2 }}
            >
                {value}
            </div>
        </div>
    );
}

function AppCard({ app, onOpen }: { app: Application; onOpen: () => void }) {
    return (
        <Card
            hover
            onClick={onOpen}
            style={{
                padding: 0,
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <div
                style={{
                    padding: '15px 16px',
                    display: 'flex',
                    gap: 12,
                    alignItems: 'flex-start',
                }}
            >
                <AppMark code={app.id} size={40} />
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            justifyContent: 'space-between',
                        }}
                    >
                        <span
                            style={{
                                fontSize: 14.5,
                                fontWeight: 700,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {app.name}
                        </span>
                        <Badge tone={APP_STATUS[app.status].tone} dot>
                            {app.status}
                        </Badge>
                    </div>
                    <div
                        className="mono"
                        style={{
                            fontSize: 11.5,
                            color: 'var(--text-3)',
                            marginTop: 2,
                        }}
                    >
                        {app.code}
                    </div>
                </div>
            </div>
            <div
                style={{
                    padding: '0 16px 12px',
                    fontSize: 12.5,
                    color: 'var(--text-2)',
                    lineHeight: 1.5,
                }}
            >
                {app.desc}
            </div>
            <div
                style={{
                    display: 'flex',
                    gap: 8,
                    padding: '0 16px 13px',
                    flexWrap: 'wrap',
                }}
            >
                <Badge tone="neutral" icon="building">
                    {app.dept}
                </Badge>
                <EnvBadge env={app.env} />
            </div>
            <div
                style={{
                    marginTop: 'auto',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3,1fr)',
                    borderTop: '1px solid var(--border)',
                    background: 'var(--surface-2)',
                }}
            >
                <Stat3 label="Projects" value={app.projects} />
                <Stat3 label="Agents" value={app.agents} border />
                <Stat3
                    label="Tools"
                    value={
                        <ToolMeter
                            req={app.toolsRequired}
                            done={app.toolsImplemented}
                        />
                    }
                    border
                />
            </div>
        </Card>
    );
}

function RegisterAppModal({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const team = useCurrentTeam();
    const form = useForm({
        name: '',
        code: '',
        environment: 'production',
        department: '',
        owner_name: '',
        owner_email: '',
        stack: '',
        region: '',
        description: '',
    });

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        if (!team) {
            return;
        }

        form.post(store([team.slug]).url, {
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
            icon="apps"
            title="Register Application"
            sub="Create a new application. Generate environment credentials from its detail page once registered."
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
                        Register Application
                    </Btn>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Field label="Application name" required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. Marine Operations Portal"
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
                    <Field
                        label="Application code"
                        required
                        hint="Lowercase, hyphenated identifier"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            placeholder="marine-ops-portal"
                        />
                        <FieldError error={form.errors.code} />
                    </Field>
                    <Field label="Environment" required>
                        <Select
                            value={form.data.environment}
                            onChange={(v) => form.setData('environment', v)}
                            options={ENV_OPTIONS}
                        />
                        <FieldError error={form.errors.environment} />
                    </Field>
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Owning department" required>
                        <Input
                            value={form.data.department}
                            onChange={(e) =>
                                form.setData('department', e.target.value)
                            }
                            placeholder="Maritime & Logistics"
                        />
                        <FieldError error={form.errors.department} />
                    </Field>
                    <Field label="Technology stack" hint="Optional">
                        <Input
                            value={form.data.stack}
                            onChange={(e) =>
                                form.setData('stack', e.target.value)
                            }
                            placeholder="Laravel · PHP 8.3"
                        />
                        <FieldError error={form.errors.stack} />
                    </Field>
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 14,
                    }}
                >
                    <Field label="Business owner" required>
                        <Input
                            value={form.data.owner_name}
                            onChange={(e) =>
                                form.setData('owner_name', e.target.value)
                            }
                            placeholder="Khalid Al-Mansoori"
                        />
                        <FieldError error={form.errors.owner_name} />
                    </Field>
                    <Field label="Owner email" required>
                        <Input
                            type="email"
                            value={form.data.owner_email}
                            onChange={(e) =>
                                form.setData('owner_email', e.target.value)
                            }
                            placeholder="name@milaha.com"
                        />
                        <FieldError error={form.errors.owner_email} />
                    </Field>
                </div>
                <Field label="Region" hint="Optional">
                    <Input
                        value={form.data.region}
                        onChange={(e) => form.setData('region', e.target.value)}
                        placeholder="Qatar — Doha DC"
                    />
                </Field>
                <Field label="Description">
                    <Textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What does this application do?"
                    />
                </Field>
                <div
                    style={{
                        display: 'flex',
                        gap: 10,
                        padding: '11px 13px',
                        background: 'var(--primary-soft)',
                        borderRadius: 'var(--r-md)',
                        border: '1px solid var(--primary-soft-2)',
                    }}
                >
                    <Icon
                        name="info"
                        size={17}
                        style={{
                            color: 'var(--primary)',
                            flexShrink: 0,
                            marginTop: 1,
                        }}
                    />
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                        }}
                    >
                        After registration, open the application and generate a{' '}
                        <b>Client ID</b> and <b>Client Secret</b> per
                        environment. The application installs the MAACC SDK and
                        configures these credentials to connect.
                    </div>
                </div>
            </div>
        </Modal>
    );
}

/* ---- Page ---- */

export default function Applications() {
    const { go, scope } = useMaaccNav();
    const [view, setView] = useState('grid');
    const [q, setQ] = useState('');
    const [dept, setDept] = useState('All departments');
    const [showReg, setShowReg] = useState(false);

    const depts = [
        'All departments',
        ...Array.from(new Set(scope.apps.map((a) => a.dept))),
    ];
    const list = scope.apps.filter(
        (a) =>
            (dept === 'All departments' || a.dept === dept) &&
            (a.name.toLowerCase().includes(q.toLowerCase()) ||
                a.code.includes(q.toLowerCase())),
    );

    return (
        <>
            <Head title="Applications" />
            <div className="route-anim">
                <PageHeader
                    title="Applications"
                    sub="Company systems registered with MAACC. Each receives credentials and implements its own client-side tools through the SDK."
                    actions={
                        <Btn
                            variant="primary"
                            icon="plus"
                            onClick={() => setShowReg(true)}
                        >
                            Register Application
                        </Btn>
                    }
                />

                <div
                    style={{
                        display: 'flex',
                        gap: 9,
                        marginBottom: 14,
                        alignItems: 'center',
                        flexWrap: 'wrap',
                    }}
                >
                    <div style={{ position: 'relative', width: 280 }}>
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
                            aria-label="Search applications"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search applications…"
                            className="maacc-input"
                            style={{ ...inputStyle, paddingLeft: 34 }}
                        />
                    </div>
                    <Select
                        ariaLabel="Filter applications by department"
                        value={dept}
                        onChange={setDept}
                        options={depts}
                        style={{ width: 200 }}
                    />
                    <div style={{ flex: 1 }} />
                    <Segmented
                        options={[
                            { value: 'grid', icon: 'grid' },
                            { value: 'list', icon: 'list' },
                        ]}
                        value={view}
                        onChange={setView}
                    />
                </div>

                {list.length === 0 && (
                    <Card>
                        <EmptyState
                            icon="apps"
                            title="No applications found"
                            desc="Try a different search or department filter."
                        />
                    </Card>
                )}

                {view === 'grid' && list.length > 0 && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(380px, 1fr))',
                            gap: 12,
                        }}
                    >
                        {list.map((a) => (
                            <AppCard
                                key={a.id}
                                app={a}
                                onOpen={() => go('application', { id: a.id })}
                            />
                        ))}
                    </div>
                )}

                {view === 'list' && list.length > 0 && (
                    <Table
                        columns={[
                            { label: 'Application' },
                            { label: 'Department' },
                            { label: 'Env' },
                            { label: 'Status' },
                            { label: 'Projects', align: 'right' },
                            { label: 'Agents', align: 'right' },
                            { label: 'Client tools', align: 'center' },
                            { label: 'Last connected', align: 'right' },
                        ]}
                    >
                        {list.map((a) => (
                            <Tr
                                key={a.id}
                                onClick={() => go('application', { id: a.id })}
                            >
                                <Td strong>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <AppMark code={a.id} />
                                        <div>
                                            <div
                                                style={{
                                                    color: 'var(--text)',
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {a.name}
                                            </div>
                                            <div
                                                className="mono"
                                                style={{
                                                    fontSize: 11,
                                                    color: 'var(--text-3)',
                                                }}
                                            >
                                                {a.code}
                                            </div>
                                        </div>
                                    </div>
                                </Td>
                                <Td>{a.dept}</Td>
                                <Td>
                                    <EnvBadge env={a.env} />
                                </Td>
                                <Td>
                                    <Badge tone={APP_STATUS[a.status].tone} dot>
                                        {a.status}
                                    </Badge>
                                </Td>
                                <Td align="right" mono strong>
                                    {a.projects}
                                </Td>
                                <Td align="right" mono strong>
                                    {a.agents}
                                </Td>
                                <Td align="center">
                                    <ToolMeter
                                        req={a.toolsRequired}
                                        done={a.toolsImplemented}
                                    />
                                </Td>
                                <Td
                                    align="right"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    {a.lastConnected}
                                </Td>
                            </Tr>
                        ))}
                    </Table>
                )}

                <RegisterAppModal
                    open={showReg}
                    onClose={() => setShowReg(false)}
                />
            </div>
        </>
    );
}
