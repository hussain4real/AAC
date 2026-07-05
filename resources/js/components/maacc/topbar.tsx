/* ============================================================
   MAACC — App shell topbar (search, persona pill, environment,
   theme toggle, notifications). Bespoke chrome from prototype.
   ============================================================ */
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import { useMaaccData } from '@/maacc/use-data';
import { logout } from '@/routes';
import { Avatar, Badge, inputStyle, Select } from './ui';

function NotifMenu({
    onClose,
    go,
}: {
    onClose: () => void;
    go: (name: 'governance') => void;
}) {
    const MAACC = useMaaccData();
    const ref = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const h = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        };
        const id = setTimeout(
            () => document.addEventListener('mousedown', h),
            0,
        );

        return () => {
            clearTimeout(id);
            document.removeEventListener('mousedown', h);
        };
    }, [onClose]);

    return (
        <div
            ref={ref}
            onClick={(e) => e.stopPropagation()}
            style={{
                position: 'absolute',
                top: 46,
                right: 0,
                width: 340,
                background: 'var(--surface)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-lg)',
                boxShadow: 'var(--sh-pop)',
                zIndex: 60,
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    padding: '12px 14px',
                    borderBottom: '1px solid var(--border)',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                }}
            >
                <span style={{ fontSize: 13, fontWeight: 700 }}>
                    Notifications
                </span>
                {MAACC.dashboard.alerts.length > 0 && (
                    <Badge tone="orange">
                        {MAACC.dashboard.alerts.length} new
                    </Badge>
                )}
            </div>
            <div style={{ maxHeight: 340, overflowY: 'auto' }}>
                {MAACC.dashboard.alerts.map((a, i) => (
                    <div
                        key={i}
                        onClick={() => {
                            go('governance');
                            onClose();
                        }}
                        className="maacc-row"
                        style={{
                            display: 'flex',
                            gap: 10,
                            padding: '11px 14px',
                            borderBottom: '1px solid var(--border)',
                            cursor: 'pointer',
                        }}
                    >
                        <span
                            style={{
                                width: 28,
                                height: 28,
                                borderRadius: 7,
                                flexShrink: 0,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background:
                                    a.sev === 'high'
                                        ? 'var(--red-100)'
                                        : a.sev === 'med'
                                          ? 'var(--orange-100)'
                                          : 'var(--primary-soft)',
                                color:
                                    a.sev === 'high'
                                        ? 'var(--red-600)'
                                        : a.sev === 'med'
                                          ? 'var(--orange-600)'
                                          : 'var(--primary)',
                            }}
                        >
                            <Icon name={a.icon} size={15} />
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: 12.5, fontWeight: 600 }}>
                                {a.title}
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--text-3)',
                                    marginTop: 1,
                                }}
                            >
                                {a.time}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function ProfileMenu({ onClose }: { onClose: () => void }) {
    const user = usePage().props.auth.user;
    const ref = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const h = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        };
        const id = setTimeout(
            () => document.addEventListener('mousedown', h),
            0,
        );

        return () => {
            clearTimeout(id);
            document.removeEventListener('mousedown', h);
        };
    }, [onClose]);

    return (
        <div
            ref={ref}
            onClick={(e) => e.stopPropagation()}
            style={{
                position: 'absolute',
                top: 46,
                right: 0,
                width: 250,
                background: 'var(--surface)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-lg)',
                boxShadow: 'var(--sh-pop)',
                zIndex: 60,
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    padding: '13px 14px',
                    borderBottom: '1px solid var(--border)',
                }}
            >
                <Avatar name={user.name} size={38} />
                <div style={{ minWidth: 0 }}>
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 700,
                            color: 'var(--text)',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {user.name}
                    </div>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--text-3)',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {user.email}
                    </div>
                </div>
            </div>
            <div style={{ padding: 6 }}>
                <Link
                    href={logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                    className="maacc-row"
                    data-test="logout-button"
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '9px 10px',
                        border: 'none',
                        background: 'none',
                        cursor: 'pointer',
                        borderRadius: 7,
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: 'var(--red-600)',
                        textAlign: 'left',
                    }}
                >
                    <Icon name="power" size={15} /> Sign out
                </Link>
            </div>
        </div>
    );
}

export function Topbar() {
    const { go, persona, env, setEnv, theme, setTheme } = useMaaccNav();
    const MAACC = useMaaccData();
    const user = usePage().props.auth.user;
    const [notifOpen, setNotifOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const alertCount = MAACC.dashboard.alerts.length;

    return (
        <header
            style={{
                height: 56,
                flexShrink: 0,
                background: 'var(--surface)',
                borderBottom: '1px solid var(--border)',
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                padding: '0 20px',
                position: 'relative',
                zIndex: 30,
            }}
        >
            <div style={{ position: 'relative', flex: 1, maxWidth: 420 }}>
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
                    placeholder="Search agents, tools, applications, runs…"
                    className="maacc-input"
                    style={{
                        ...inputStyle,
                        height: 36,
                        paddingLeft: 34,
                        background: 'var(--surface-2)',
                        border: '1px solid var(--border)',
                    }}
                />
                <span
                    style={{
                        position: 'absolute',
                        right: 9,
                        top: '50%',
                        transform: 'translateY(-50%)',
                        fontSize: 11,
                        fontWeight: 600,
                        color: 'var(--text-3)',
                        border: '1px solid var(--border-2)',
                        borderRadius: 5,
                        padding: '1px 6px',
                        fontFamily: 'var(--mono)',
                    }}
                >
                    ⌘K
                </span>
            </div>
            <div style={{ flex: 1 }} />
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span
                    className="maacc-tip"
                    data-tip={persona.blurb}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        height: 32,
                        padding: '0 11px',
                        borderRadius: 999,
                        border: `1px solid ${persona.tone}`,
                        background: 'var(--surface-2)',
                        fontSize: 12,
                        fontWeight: 600,
                        color: 'var(--text)',
                        cursor: 'help',
                    }}
                >
                    <span
                        style={{
                            width: 7,
                            height: 7,
                            borderRadius: 7,
                            background: persona.tone,
                        }}
                    />
                    {persona.view}
                </span>
                <Select
                    value={env}
                    onChange={(v) => setEnv(v as typeof env)}
                    options={[
                        { value: 'Production', label: '⬤ Production' },
                        { value: 'Staging', label: '⬤ Staging' },
                        { value: 'Development', label: '⬤ Development' },
                    ]}
                    style={{ width: 150 }}
                />
                <button
                    onClick={() =>
                        setTheme(theme === 'light' ? 'dark' : 'light')
                    }
                    className="maacc-iconbtn"
                    style={{
                        width: 36,
                        height: 36,
                        borderRadius: 8,
                        border: '1px solid var(--border)',
                        background: 'var(--surface-2)',
                        color: 'var(--text-2)',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                    title="Toggle theme"
                >
                    <Icon name={theme === 'light' ? 'moon' : 'sun'} size={17} />
                </button>
                <div style={{ position: 'relative' }}>
                    <button
                        onMouseDown={(e) => e.stopPropagation()}
                        onClick={() => {
                            setNotifOpen(!notifOpen);
                            setProfileOpen(false);
                        }}
                        className="maacc-iconbtn"
                        style={{
                            width: 36,
                            height: 36,
                            borderRadius: 8,
                            border: '1px solid var(--border)',
                            background: 'var(--surface-2)',
                            color: 'var(--text-2)',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            position: 'relative',
                        }}
                    >
                        <Icon name="bell" size={17} />
                        {alertCount > 0 && (
                            <span
                                style={{
                                    position: 'absolute',
                                    top: 7,
                                    right: 8,
                                    width: 7,
                                    height: 7,
                                    borderRadius: 7,
                                    background: 'var(--orange-600)',
                                    border: '2px solid var(--surface)',
                                }}
                            />
                        )}
                    </button>
                    {notifOpen && (
                        <NotifMenu
                            onClose={() => setNotifOpen(false)}
                            go={go}
                        />
                    )}
                </div>
                <div style={{ position: 'relative' }}>
                    <button
                        onMouseDown={(e) => e.stopPropagation()}
                        onClick={() => {
                            setProfileOpen(!profileOpen);
                            setNotifOpen(false);
                        }}
                        className="maacc-iconbtn"
                        title={user.name}
                        style={{
                            width: 36,
                            height: 36,
                            borderRadius: 999,
                            border: '1px solid var(--border)',
                            background: 'var(--surface-2)',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            padding: 0,
                        }}
                    >
                        <Avatar name={user.name} size={28} />
                    </button>
                    {profileOpen && (
                        <ProfileMenu onClose={() => setProfileOpen(false)} />
                    )}
                </div>
            </div>
        </header>
    );
}
