/* ============================================================
   MAAC — Settings
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Progress } from '@/components/maac/charts';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    Field,
    Input,
    PageHeader,
    SectionHeader,
    Select,
    Table,
    Tabs,
    Td,
    Toggle,
    Tr,
} from '@/components/maac/ui';
import type { TabDef } from '@/components/maac/ui';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

/* ── Local sub-components ─────────────────────────────────── */

function SettingRow({
    label,
    desc,
    on,
    border,
}: {
    label: string;
    desc: string;
    on: boolean;
    border: boolean;
}) {
    const [v, setV] = useState(on);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                padding: '13px 0',
                borderTop: border ? '1px solid var(--border)' : 'none',
            }}
        >
            <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{label}</div>
                <div
                    style={{
                        fontSize: 12,
                        color: 'var(--text-3)',
                        marginTop: 2,
                    }}
                >
                    {desc}
                </div>
            </div>
            <Toggle on={v} onChange={setV} />
        </div>
    );
}

function UsageBar({
    label,
    v,
    max,
    suffix,
}: {
    label: string;
    v: number;
    max: number;
    suffix?: string;
}) {
    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    fontSize: 11.5,
                    marginBottom: 5,
                }}
            >
                <span style={{ color: 'var(--text-2)' }}>{label}</span>
                <span className="tnum" style={{ fontWeight: 600 }}>
                    {v}
                    {suffix ?? ` / ${max}`}
                </span>
            </div>
            <Progress value={v} max={max} color="var(--primary)" height={6} />
        </div>
    );
}

function GeneralSettings() {
    const MAAC = useMaacData();

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '1fr 320px',
                gap: 14,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Platform" icon="settings" />
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                            maxWidth: 480,
                        }}
                    >
                        <Field label="Platform name">
                            <Input defaultValue="Milaha AI Agent Center" />
                        </Field>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Field label="Default region">
                                <Select
                                    value="Qatar — Doha DC"
                                    onChange={() => {}}
                                    options={[
                                        'Qatar — Doha DC',
                                        'Qatar — Doha DR',
                                    ]}
                                />
                            </Field>
                            <Field label="Default model">
                                <Select
                                    value="GPT-4o"
                                    onChange={() => {}}
                                    options={MAAC.llms
                                        .filter((l) => l.status === 'Approved')
                                        .map((l) => l.name)}
                                />
                            </Field>
                        </div>
                    </div>
                </Card>
                <Card>
                    <SectionHeader title="Defaults" icon="bolt" />
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        {[
                            {
                                label: 'Require approval before production',
                                desc: 'Agents & tools need owner approval before Production.',
                                on: true,
                            },
                            {
                                label: 'Mask sensitive tool results in logs',
                                desc: 'Restricted & Confidential outputs masked by default.',
                                on: true,
                            },
                            {
                                label: 'Auto-generate SDK stubs',
                                desc: 'Generate stubs when a client-side tool is created.',
                                on: true,
                            },
                            {
                                label: 'Pending tool timeout (60s)',
                                desc: 'Expire runs that wait too long for a client tool.',
                                on: true,
                            },
                        ].map((s, i) => (
                            <SettingRow key={i} {...s} border={i > 0} />
                        ))}
                    </div>
                </Card>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Plan" icon="sparkles" />
                    <div style={{ fontSize: 13, fontWeight: 700 }}>
                        Enterprise — Internal
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-3)',
                            marginTop: 2,
                        }}
                    >
                        Unlimited applications & agents
                    </div>
                    <div
                        style={{
                            marginTop: 13,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 9,
                        }}
                    >
                        <UsageBar label="Applications" v={5} max={50} />
                        <UsageBar label="Agents" v={8} max={100} />
                        <UsageBar
                            label="Daily token budget"
                            v={64}
                            max={100}
                            suffix="%"
                        />
                    </div>
                </Card>
                <Card>
                    <SectionHeader title="Support" icon="info" />
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        <Btn variant="soft" size="sm" full icon="book">
                            Documentation
                        </Btn>
                        <Btn variant="default" size="sm" full icon="send">
                            Contact platform team
                        </Btn>
                    </div>
                </Card>
            </div>
        </div>
    );
}

type Member = { name: string; email: string; role: string; apps: string };

function MembersSettings() {
    const members: Member[] = [
        {
            name: 'Reema Saleh',
            email: 'r.saleh@milaha.com',
            role: 'Agent Developer',
            apps: 'MOP, FWS',
        },
        {
            name: 'Khalid Al-Mansoori',
            email: 'k.almansoori@milaha.com',
            role: 'Application Owner',
            apps: 'MOP',
        },
        {
            name: 'Aisha Rahman',
            email: 'a.rahman@milaha.com',
            role: 'Project Owner',
            apps: 'FWS',
        },
        {
            name: 'Sami Diab',
            email: 's.diab@milaha.com',
            role: 'Security Reviewer',
            apps: 'All',
        },
        {
            name: 'Yousef Haddad',
            email: 'y.haddad@milaha.com',
            role: 'Agent Developer',
            apps: 'PMA',
        },
        {
            name: 'Omar Sheikh',
            email: 'o.sheikh@milaha.com',
            role: 'Business Viewer',
            apps: 'VMS',
        },
    ];

    return (
        <Card pad={false}>
            <div
                style={{
                    padding: '14px 16px 12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <SectionHeader
                    title="Members"
                    sub={`${members.length} people with platform access`}
                    icon="user"
                    style={{ marginBottom: 0 }}
                />
                <Btn variant="primary" size="sm" icon="plus">
                    Invite member
                </Btn>
            </div>
            <Table
                columns={[
                    { label: 'Member' },
                    { label: 'Role' },
                    { label: 'Applications' },
                    { label: '', align: 'right' },
                ]}
            >
                {members.map((m) => (
                    <Tr key={m.email}>
                        <Td strong>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <Avatar name={m.name} size={30} />
                                <div>
                                    <div
                                        style={{
                                            fontWeight: 600,
                                            color: 'var(--text)',
                                        }}
                                    >
                                        {m.name}
                                    </div>
                                    <div
                                        className="mono"
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--text-3)',
                                        }}
                                    >
                                        {m.email}
                                    </div>
                                </div>
                            </div>
                        </Td>
                        <Td>
                            <Badge tone="purple" soft>
                                {m.role}
                            </Badge>
                        </Td>
                        <Td mono>{m.apps}</Td>
                        <Td align="right">
                            <button
                                className="maac-iconbtn"
                                style={{
                                    border: 'none',
                                    background: 'none',
                                    cursor: 'pointer',
                                    color: 'var(--text-3)',
                                    padding: 5,
                                    borderRadius: 6,
                                }}
                            >
                                <Icon name="dots" size={16} />
                            </button>
                        </Td>
                    </Tr>
                ))}
            </Table>
        </Card>
    );
}

type EnvEntry = {
    name: string;
    desc: string;
    apps: number;
    color: string;
    icon: string;
};

function EnvSettings() {
    const envs: EnvEntry[] = [
        {
            name: 'Production',
            desc: 'Live agents serving applications.',
            apps: 4,
            color: 'var(--purple-600)',
            icon: 'power',
        },
        {
            name: 'Staging',
            desc: 'Pre-production validation environment.',
            apps: 3,
            color: 'var(--blue-500)',
            icon: 'layers',
        },
        {
            name: 'Development',
            desc: 'Developer sandbox for building agents.',
            apps: 5,
            color: 'var(--text-3)',
            icon: 'flask',
        },
    ];

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(3,1fr)',
                gap: 14,
            }}
        >
            {envs.map((e) => (
                <Card
                    key={e.name}
                    style={{ borderTop: `3px solid ${e.color}` }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            marginBottom: 10,
                        }}
                    >
                        <span
                            style={{
                                width: 34,
                                height: 34,
                                borderRadius: 9,
                                background: 'var(--surface-3)',
                                color: e.color,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <Icon name={e.icon} size={17} />
                        </span>
                        <div style={{ fontSize: 14, fontWeight: 700 }}>
                            {e.name}
                        </div>
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: 'var(--text-2)',
                            lineHeight: 1.5,
                            marginBottom: 12,
                        }}
                    >
                        {e.desc}
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            paddingTop: 11,
                            borderTop: '1px solid var(--border)',
                        }}
                    >
                        <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            <b
                                className="tnum"
                                style={{ color: 'var(--text)' }}
                            >
                                {e.apps}
                            </b>{' '}
                            applications
                        </span>
                        <Badge tone="teal" dot>
                            Healthy
                        </Badge>
                    </div>
                </Card>
            ))}
        </div>
    );
}

function AppearanceSettings({
    theme,
    setTheme,
}: {
    theme: 'light' | 'dark';
    setTheme: (t: 'light' | 'dark') => void;
}) {
    return (
        <Card style={{ maxWidth: 620 }}>
            <SectionHeader
                title="Theme"
                sub="Choose how MAAC looks for you"
                icon="sun"
            />
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                {(
                    [
                        {
                            id: 'light',
                            label: 'Light',
                            bg: '#eef0f5',
                            fg: '#fff',
                            text: '#141b2b',
                        },
                        {
                            id: 'dark',
                            label: 'Dark',
                            bg: '#070e1b',
                            fg: '#0f1b30',
                            text: '#e7ecf5',
                        },
                    ] as const
                ).map((t) => {
                    const on = theme === t.id;

                    return (
                        <div
                            key={t.id}
                            onClick={() => setTheme(t.id)}
                            style={{
                                cursor: 'pointer',
                                padding: 14,
                                borderRadius: 'var(--r-lg)',
                                border: `1.5px solid ${on ? 'var(--primary)' : 'var(--border)'}`,
                                background: on
                                    ? 'var(--primary-soft)'
                                    : 'var(--surface)',
                            }}
                        >
                            <div
                                style={{
                                    height: 80,
                                    borderRadius: 8,
                                    background: t.bg,
                                    padding: 8,
                                    display: 'flex',
                                    gap: 6,
                                    marginBottom: 11,
                                    border: '1px solid var(--border)',
                                }}
                            >
                                <div
                                    style={{
                                        width: '28%',
                                        background: '#061731',
                                        borderRadius: 5,
                                    }}
                                />
                                <div
                                    style={{
                                        flex: 1,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 5,
                                    }}
                                >
                                    <div
                                        style={{
                                            height: 14,
                                            background: t.fg,
                                            borderRadius: 4,
                                            border: '1px solid rgba(128,128,128,.2)',
                                        }}
                                    />
                                    <div
                                        style={{
                                            flex: 1,
                                            background: t.fg,
                                            borderRadius: 4,
                                            border: '1px solid rgba(128,128,128,.2)',
                                        }}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <span style={{ fontSize: 13, fontWeight: 700 }}>
                                    {t.label}
                                </span>
                                {on && (
                                    <span
                                        style={{
                                            width: 20,
                                            height: 20,
                                            borderRadius: 999,
                                            background: 'var(--primary)',
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <Icon
                                            name="check"
                                            size={12}
                                            strokeWidth={3}
                                        />
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </Card>
    );
}

/* ── Page ─────────────────────────────────────────────────── */

export default function Settings() {
    const { theme, setTheme, scope } = useMaacNav();
    const allTabs: (TabDef & { roles: string[] })[] = [
        {
            id: 'general',
            label: 'General',
            icon: 'settings',
            roles: ['admin', 'projadmin'],
        },
        { id: 'members', label: 'Members', icon: 'user', roles: ['admin'] },
        {
            id: 'environments',
            label: 'Environments',
            icon: 'layers',
            roles: ['admin'],
        },
        {
            id: 'appearance',
            label: 'Appearance',
            icon: 'sun',
            roles: ['admin', 'projadmin', 'dev'],
        },
    ];
    const tabs = allTabs.filter((t) => t.roles.includes(scope.role.id));
    const [tab, setTab] = useState(tabs[0].id);

    return (
        <>
            <Head title="Settings" />
            <div className="route-anim">
                <PageHeader
                    title="Settings"
                    sub={
                        scope.isAll
                            ? 'Platform configuration, members, environments, and appearance.'
                            : 'Your preferences and appearance.'
                    }
                    tabs={<Tabs tabs={tabs} active={tab} onChange={setTab} />}
                />
                {tab === 'general' && <GeneralSettings />}
                {tab === 'members' && <MembersSettings />}
                {tab === 'environments' && <EnvSettings />}
                {tab === 'appearance' && (
                    <AppearanceSettings theme={theme} setTheme={setTheme} />
                )}
            </div>
        </>
    );
}
