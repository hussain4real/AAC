/* ============================================================
   MAAC — App shell sidebar (navy gradient, grouped nav,
   persona switcher). Bespoke chrome ported from the prototype.
   ============================================================ */
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { NAV_GROUPS, navAllowed, PERSONAS, SCREEN_OF } from '@/maac/personas';
import type { Persona } from '@/maac/personas';
import { useMaacData } from '@/maac/use-data';
import { Avatar, Badge } from './ui';

function Logo({ compact = false }: { compact?: boolean }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
            <div
                style={{
                    width: 34,
                    height: 34,
                    borderRadius: 9,
                    background:
                        'linear-gradient(145deg, var(--purple-500), var(--navy-900))',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                    boxShadow:
                        '0 2px 8px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.14)',
                    position: 'relative',
                    overflow: 'hidden',
                }}
            >
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M5 12h11M13 7l5 5-5 5"
                        stroke="var(--orange-600)"
                        strokeWidth="2.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </div>
            {!compact && (
                <div style={{ lineHeight: 1 }}>
                    <div
                        style={{
                            fontSize: 16,
                            fontWeight: 700,
                            color: '#fff',
                            letterSpacing: 0.3,
                        }}
                    >
                        Milaha
                    </div>
                    <div
                        style={{
                            fontSize: 9.5,
                            fontWeight: 600,
                            color: 'rgba(255,255,255,.5)',
                            letterSpacing: 1.4,
                            marginTop: 3,
                            textTransform: 'uppercase',
                        }}
                    >
                        AI Agent Center
                    </div>
                </div>
            )}
        </div>
    );
}

function AccountSwitcher({
    persona,
    setPersona,
    onClose,
}: {
    persona: Persona;
    setPersona: (p: Persona) => void;
    onClose: () => void;
}) {
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
                top: 'calc(100% - 2px)',
                left: 12,
                right: 12,
                zIndex: 80,
                background: 'var(--surface)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-lg)',
                boxShadow: 'var(--sh-pop)',
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    padding: '10px 13px 8px',
                    borderBottom: '1px solid var(--border)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 7,
                }}
            >
                <Icon name="eye" size={14} style={{ color: 'var(--text-3)' }} />
                <span
                    style={{
                        fontSize: 11,
                        fontWeight: 700,
                        color: 'var(--text-3)',
                        textTransform: 'uppercase',
                        letterSpacing: 0.5,
                    }}
                >
                    Switch view
                </span>
            </div>
            <div style={{ padding: 6 }}>
                {PERSONAS.map((p) => {
                    const on = p.id === persona.id;

                    return (
                        <button
                            key={p.id}
                            onClick={() => setPersona(p)}
                            className="maac-row"
                            style={{
                                width: '100%',
                                display: 'flex',
                                gap: 11,
                                padding: '10px 10px',
                                border: 'none',
                                background: on ? 'var(--primary-soft)' : 'none',
                                borderRadius: 9,
                                cursor: 'pointer',
                                textAlign: 'left',
                                alignItems: 'flex-start',
                            }}
                        >
                            <Avatar name={p.name} size={32} tone={p.tone} />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 7,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 700,
                                            color: 'var(--text)',
                                        }}
                                    >
                                        {p.name}
                                    </span>
                                    {on && (
                                        <Badge tone="purple" soft>
                                            Active
                                        </Badge>
                                    )}
                                </div>
                                <div
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 600,
                                        color: p.tone,
                                        marginTop: 1,
                                    }}
                                >
                                    {p.role}
                                </div>
                                <div
                                    style={{
                                        fontSize: 11,
                                        color: 'var(--text-3)',
                                        marginTop: 4,
                                        lineHeight: 1.45,
                                    }}
                                >
                                    {p.blurb}
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>
            <div
                style={{
                    padding: '8px 6px',
                    borderTop: '1px solid var(--border)',
                }}
            >
                <button
                    className="maac-row"
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '8px 10px',
                        border: 'none',
                        background: 'none',
                        cursor: 'pointer',
                        borderRadius: 7,
                        fontSize: 12.5,
                        color: 'var(--red-600)',
                        textAlign: 'left',
                    }}
                >
                    <Icon name="power" size={15} /> Sign out
                </button>
            </div>
        </div>
    );
}

export function Sidebar() {
    const { go, persona, setPersona, activeScreen } = useMaacNav();
    const MAAC = useMaacData();
    // System status reflects real governance/observability alerts: a high-
    // severity alert degrades the footer indicator.
    const degraded = MAAC.dashboard.alerts.some((a) => a.sev === 'high');
    const [acctOpen, setAcctOpen] = useState(false);
    // Platform-administration nav items are additionally gated on the real
    // global RBAC, so they appear only for actual MAAC platform admins — not
    // merely whoever selects the admin persona (Phase 8B).
    const platformPermissions =
        usePage().props.auth.platform?.permissions ?? [];
    const visibleGroups = NAV_GROUPS.map((g) => ({
        ...g,
        items: g.items.filter(
            (it) =>
                navAllowed(persona.id, it.id) &&
                (!it.permission || platformPermissions.includes(it.permission)),
        ),
    })).filter((g) => g.items.length);

    return (
        <aside
            style={{
                width: 'var(--sidebar-w)',
                flexShrink: 0,
                background:
                    'linear-gradient(180deg, #061731, #04122a 60%, #050d1e)',
                display: 'flex',
                flexDirection: 'column',
                borderRight: '1px solid rgba(255,255,255,.06)',
                position: 'relative',
                zIndex: 10,
            }}
        >
            <div style={{ padding: '16px 16px 12px' }}>
                <Logo />
            </div>

            {/* account / persona switcher */}
            <div style={{ padding: '0 12px 10px', position: 'relative' }}>
                <button
                    onMouseDown={(e) => e.stopPropagation()}
                    onClick={() => setAcctOpen((o) => !o)}
                    className="maac-navitem"
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '8px 10px',
                        borderRadius: 10,
                        border: '1px solid rgba(255,255,255,.1)',
                        background: acctOpen
                            ? 'rgba(255,255,255,.08)'
                            : 'rgba(255,255,255,.04)',
                        cursor: 'pointer',
                        transition: 'background .12s',
                    }}
                >
                    <Avatar name={persona.name} size={32} tone={persona.tone} />
                    <div
                        style={{
                            flex: 1,
                            minWidth: 0,
                            textAlign: 'left',
                            lineHeight: 1.25,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 12.5,
                                fontWeight: 700,
                                color: '#fff',
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {persona.name}
                        </div>
                        <div
                            style={{
                                fontSize: 10.5,
                                color: 'rgba(255,255,255,.55)',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 5,
                            }}
                        >
                            <span
                                style={{
                                    width: 6,
                                    height: 6,
                                    borderRadius: 6,
                                    background: persona.tone,
                                }}
                            />
                            {persona.role}
                        </div>
                    </div>
                    <Icon
                        name="chevdown"
                        size={15}
                        style={{
                            color: 'rgba(255,255,255,.5)',
                            transform: acctOpen ? 'rotate(180deg)' : 'none',
                            transition: 'transform .15s',
                        }}
                    />
                </button>
                {acctOpen && (
                    <AccountSwitcher
                        persona={persona}
                        setPersona={(p) => {
                            setPersona(p);
                            setAcctOpen(false);
                        }}
                        onClose={() => setAcctOpen(false)}
                    />
                )}
            </div>

            <div style={{ padding: '0 12px 8px' }}>
                <button
                    onClick={() => go('createAgent')}
                    className="maac-navitem"
                    style={{
                        width: '100%',
                        height: 36,
                        borderRadius: 8,
                        border: '1px solid rgba(255,255,255,.12)',
                        background: 'rgba(255,255,255,.04)',
                        color: '#fff',
                        fontSize: 12.5,
                        fontWeight: 600,
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 7,
                        transition: 'background .12s',
                    }}
                >
                    <Icon name="plus" size={15} strokeWidth={2.2} /> New Agent
                </button>
            </div>

            <nav
                className="maac-scroll"
                style={{ flex: 1, overflowY: 'auto', padding: '6px 12px 14px' }}
            >
                {visibleGroups.map((grp, gi) => (
                    <div key={gi} style={{ marginBottom: 14 }}>
                        {grp.title && (
                            <div
                                style={{
                                    fontSize: 10,
                                    fontWeight: 700,
                                    color: 'rgba(255,255,255,.34)',
                                    textTransform: 'uppercase',
                                    letterSpacing: 1,
                                    padding: '6px 10px 5px',
                                }}
                            >
                                {grp.title}
                            </div>
                        )}
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 1,
                            }}
                        >
                            {grp.items.map((it) => {
                                const on =
                                    (SCREEN_OF[activeScreen] ??
                                        activeScreen) === it.id;

                                return (
                                    <button
                                        key={it.id}
                                        onClick={() => go(it.id)}
                                        className="maac-navitem"
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 11,
                                            padding: '8px 10px',
                                            borderRadius: 7,
                                            border: 'none',
                                            cursor: 'pointer',
                                            fontSize: 13,
                                            fontWeight: on ? 600 : 500,
                                            textAlign: 'left',
                                            background: on
                                                ? 'linear-gradient(90deg, rgba(123,46,174,.45), rgba(123,46,174,.16))'
                                                : 'transparent',
                                            color: on
                                                ? '#fff'
                                                : 'rgba(255,255,255,.62)',
                                            position: 'relative',
                                        }}
                                    >
                                        {on && (
                                            <span
                                                style={{
                                                    position: 'absolute',
                                                    left: 0,
                                                    top: 7,
                                                    bottom: 7,
                                                    width: 3,
                                                    borderRadius: 3,
                                                    background:
                                                        'var(--orange-600)',
                                                }}
                                            />
                                        )}
                                        <Icon
                                            name={it.icon}
                                            size={17}
                                            strokeWidth={on ? 2 : 1.7}
                                            style={{
                                                color: on
                                                    ? 'var(--orange-400)'
                                                    : 'rgba(255,255,255,.5)',
                                            }}
                                        />
                                        {it.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </nav>

            <div
                style={{
                    padding: '12px 16px',
                    borderTop: '1px solid rgba(255,255,255,.07)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                }}
            >
                <span
                    style={{
                        width: 8,
                        height: 8,
                        borderRadius: 8,
                        background: degraded
                            ? 'var(--orange-500)'
                            : 'var(--teal-400)',
                        boxShadow: degraded
                            ? '0 0 0 3px rgba(245,158,11,.2)'
                            : '0 0 0 3px rgba(60,191,174,.2)',
                    }}
                />
                <div
                    style={{
                        fontSize: 11,
                        color: 'rgba(255,255,255,.55)',
                        lineHeight: 1.3,
                    }}
                >
                    <div
                        style={{
                            color: 'rgba(255,255,255,.8)',
                            fontWeight: 600,
                        }}
                    >
                        {degraded
                            ? 'Service degraded'
                            : 'All systems operational'}
                    </div>
                    <div>MAAC v1.1 · Doha DC</div>
                </div>
            </div>
        </aside>
    );
}
