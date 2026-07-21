/* ============================================================
   MAACC — App shell sidebar (navy gradient, grouped nav,
   persona switcher). Bespoke chrome ported from the prototype.
   ============================================================ */
import { usePage } from '@inertiajs/react';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';
import type { RouteName } from '@/maacc/nav';
import { NAV_GROUPS, SCREEN_OF } from '@/maacc/personas';
import { useMaaccData } from '@/maacc/use-data';
import { Avatar } from './ui';

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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <circle
                        cx="12"
                        cy="12"
                        r="7.5"
                        stroke="#fff"
                        strokeWidth="1.5"
                        fill="none"
                    />
                    <circle cx="12" cy="4.5" r="1.85" fill="#fff" />
                    <circle cx="5.5" cy="15.75" r="1.85" fill="#fff" />
                    <circle cx="18.5" cy="15.75" r="1.85" fill="#fff" />
                    <circle cx="12" cy="12" r="1.95" fill="var(--orange-600)" />
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
                        MAACC
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
                        Multi Agent AI Control Centre
                    </div>
                </div>
            )}
        </div>
    );
}

export function Sidebar({
    className = '',
    onNavigate,
}: {
    className?: string;
    onNavigate?: () => void;
}) {
    const { go, persona, activeScreen } = useMaaccNav();
    const MAACC = useMaaccData();
    // System status reflects real governance/observability alerts: a high-
    // severity alert degrades the footer indicator.
    const degraded = MAACC.dashboard.alerts.some((a) => a.sev === 'high');
    // Platform-administration nav items are additionally gated on the real
    // global RBAC, so they appear only for actual MAACC platform admins — not
    // merely whoever selects the admin persona (Phase 8B).
    const platformPermissions =
        usePage().props.auth.platform?.permissions ?? [];
    const authorizedNavigation = usePage().props.auth.maacc.navigation;
    const canCreateAgent =
        usePage().props.auth.maacc.permissions.includes('agent:manage');
    const visibleGroups = NAV_GROUPS.map((g) => ({
        ...g,
        items: g.items.filter(
            (it) =>
                authorizedNavigation.includes(it.id) &&
                (!it.permission || platformPermissions.includes(it.permission)),
        ),
    })).filter((g) => g.items.length);

    const navigate = (screen: RouteName) => {
        go(screen);
        onNavigate?.();
    };

    return (
        <aside
            className={className}
            aria-label="Primary navigation"
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

            {/* Authenticated actor and server-issued access role. */}
            <div style={{ padding: '0 12px 10px', position: 'relative' }}>
                <div
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '8px 10px',
                        borderRadius: 10,
                        border: '1px solid rgba(255,255,255,.1)',
                        background: 'rgba(255,255,255,.04)',
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
                </div>
            </div>

            {canCreateAgent && (
                <div style={{ padding: '0 12px 8px' }}>
                    <button
                        onClick={() => navigate('createAgent')}
                        className="maacc-navitem"
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
                        <Icon name="plus" size={15} strokeWidth={2.2} /> New
                        Agent
                    </button>
                </div>
            )}

            <nav
                aria-label="MAACC console"
                className="maacc-scroll"
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
                                        onClick={() => navigate(it.id)}
                                        aria-current={on ? 'page' : undefined}
                                        className="maacc-navitem"
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
                            ? 'Governance attention required'
                            : 'No governance alerts'}
                    </div>
                    <div>MAACC v1.1 · Doha DC</div>
                </div>
            </div>
        </aside>
    );
}
