/* ============================================================
   MAAC — App shell topbar (search, persona pill, environment,
   theme toggle, notifications). Bespoke chrome from prototype.
   ============================================================ */
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';
import { Badge, inputStyle, Select } from './ui';

function NotifMenu({
    onClose,
    go,
}: {
    onClose: () => void;
    go: (name: 'governance') => void;
}) {
    const MAAC = useMaacData();
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
                <Badge tone="orange">4 new</Badge>
            </div>
            <div style={{ maxHeight: 340, overflowY: 'auto' }}>
                {MAAC.dashboard.alerts.map((a, i) => (
                    <div
                        key={i}
                        onClick={() => {
                            go('governance');
                            onClose();
                        }}
                        className="maac-row"
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

export function Topbar() {
    const { go, persona, env, setEnv, theme, setTheme } = useMaacNav();
    const [notifOpen, setNotifOpen] = useState(false);

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
                    className="maac-input"
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
                    className="maac-tip"
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
                    className="maac-iconbtn"
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
                        onClick={() => setNotifOpen(!notifOpen)}
                        className="maac-iconbtn"
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
                    </button>
                    {notifOpen && (
                        <NotifMenu
                            onClose={() => setNotifOpen(false)}
                            go={go}
                        />
                    )}
                </div>
            </div>
        </header>
    );
}
