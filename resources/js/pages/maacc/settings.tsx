/* ============================================================
   MAACC — Settings
   Members, governance defaults, usage, and environments are read from
   real backend data (the `members` page prop + the shared `maacc` prop);
   the platform name reflects the current team.
   ============================================================ */
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    Field,
    Input,
    PageHeader,
    SectionHeader,
    Table,
    Tabs,
    Td,
    Tr,
} from '@/components/maacc/ui';
import type { TabDef } from '@/components/maacc/ui';
import type { Environment } from '@/maacc/data';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';

/* ── Local sub-components ─────────────────────────────────── */

function UsageStat({ label, value }: { label: string; value: number }) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                fontSize: 12.5,
            }}
        >
            <span style={{ color: 'var(--text-2)' }}>{label}</span>
            <span className="tnum" style={{ fontWeight: 700, fontSize: 14 }}>
                {value.toLocaleString()}
            </span>
        </div>
    );
}

function GeneralSettings() {
    const MAACC = useMaaccData();
    const { currentTeam } = usePage().props;
    const settings = MAACC.governanceSettings;

    const defaults = [
        {
            label: 'Mask sensitive inputs in logs',
            desc: 'Prompts and tool arguments marked sensitive are masked before logging.',
            on: settings.maskSensitiveInputs,
        },
        {
            label: 'Mask sensitive tool results in logs',
            desc: 'Restricted & Confidential tool outputs are masked before logging.',
            on: settings.maskSensitiveOutputs,
        },
        {
            label: 'Block restricted data from logs',
            desc: 'Restricted-sensitivity payloads are never written to run logs.',
            on: settings.blockRestrictedLogging,
        },
    ];

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
                    <div style={{ maxWidth: 480 }}>
                        <Field label="Team name">
                            <Input value={currentTeam?.name ?? ''} readOnly />
                        </Field>
                    </div>
                </Card>
                <Card>
                    <SectionHeader
                        title="Data governance defaults"
                        icon="shield"
                    />
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        {defaults.map((s, i) => (
                            <div
                                key={s.label}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 14,
                                    padding: '13px 0',
                                    borderTop: i
                                        ? '1px solid var(--border)'
                                        : 'none',
                                }}
                            >
                                <div style={{ flex: 1 }}>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 600,
                                        }}
                                    >
                                        {s.label}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: 'var(--text-3)',
                                            marginTop: 2,
                                        }}
                                    >
                                        {s.desc}
                                    </div>
                                </div>
                                <Badge tone={s.on ? 'teal' : 'neutral'} dot>
                                    {s.on ? 'On' : 'Off'}
                                </Badge>
                            </div>
                        ))}
                    </div>
                    <div
                        style={{
                            marginTop: 12,
                            fontSize: 11.5,
                            color: 'var(--text-3)',
                        }}
                    >
                        Configured on the Governance page.
                    </div>
                </Card>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Card>
                    <SectionHeader title="Usage" icon="sparkles" />
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 10,
                        }}
                    >
                        <UsageStat
                            label="Applications"
                            value={MAACC.apps.length}
                        />
                        <UsageStat label="Agents" value={MAACC.agents.length} />
                        <UsageStat
                            label="Projects"
                            value={MAACC.projects.length}
                        />
                        <UsageStat label="Tools" value={MAACC.tools.length} />
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

type Member = { name: string; email: string; role: string };

function MembersSettings({ members }: { members: Member[] }) {
    return (
        <Card pad={false}>
            <div style={{ padding: '14px 16px 12px' }}>
                <SectionHeader
                    title="Members"
                    sub={`${members.length} ${members.length === 1 ? 'person' : 'people'} with access to this team`}
                    icon="user"
                    style={{ marginBottom: 0 }}
                />
            </div>
            <Table columns={[{ label: 'Member' }, { label: 'Role' }]}>
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
                    </Tr>
                ))}
            </Table>
        </Card>
    );
}

function EnvSettings() {
    const MAACC = useMaaccData();
    const envMeta: {
        name: Environment;
        desc: string;
        color: string;
        icon: string;
    }[] = [
        {
            name: 'Production',
            desc: 'Live agents serving applications.',
            color: 'var(--purple-600)',
            icon: 'power',
        },
        {
            name: 'Staging',
            desc: 'Pre-production validation environment.',
            color: 'var(--blue-500)',
            icon: 'layers',
        },
        {
            name: 'Development',
            desc: 'Developer sandbox for building agents.',
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
            {envMeta.map((e) => {
                const count = MAACC.apps.filter((a) => a.env === e.name).length;

                return (
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
                                paddingTop: 11,
                                borderTop: '1px solid var(--border)',
                            }}
                        >
                            <span
                                style={{ fontSize: 12, color: 'var(--text-3)' }}
                            >
                                <b
                                    className="tnum"
                                    style={{ color: 'var(--text)' }}
                                >
                                    {count}
                                </b>{' '}
                                {count === 1 ? 'application' : 'applications'}
                            </span>
                        </div>
                    </Card>
                );
            })}
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
                sub="Choose how MAACC looks for you"
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

export default function Settings({ members }: { members: Member[] }) {
    const { theme, setTheme, scope } = useMaaccNav();
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
                {tab === 'members' && <MembersSettings members={members} />}
                {tab === 'environments' && <EnvSettings />}
                {tab === 'appearance' && (
                    <AppearanceSettings theme={theme} setTheme={setTheme} />
                )}
            </div>
        </>
    );
}
