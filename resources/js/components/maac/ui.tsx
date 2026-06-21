/* ============================================================
   MAAC — UI primitives
   Faithful port of the handoff prototype's design system, tuned
   to the Milaha tokens in app.css (.maac-theme). Interactive
   atoms that benefit from Radix behavior are built on shadcn:
   `Modal` → shadcn Dialog, `Select` → shadcn Select. Presentational
   atoms are styled directly with the token system for exact fidelity.
   ============================================================ */
import type { CSSProperties, ReactNode } from 'react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select as ShadSelect,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { execModeLabel } from '@/maac/data';
import { Icon } from '@/maac/icons';

/* ---------- tones ---------- */
export type Tone =
    | 'neutral'
    | 'purple'
    | 'teal'
    | 'orange'
    | 'red'
    | 'amber'
    | 'blue';
export const TONES: Record<Tone, { bg: string; fg: string; bd: string }> = {
    neutral: {
        bg: 'var(--surface-3)',
        fg: 'var(--text-2)',
        bd: 'var(--border-2)',
    },
    purple: {
        bg: 'var(--primary-soft)',
        fg: 'var(--primary)',
        bd: 'var(--primary-soft-2)',
    },
    teal: {
        bg: 'var(--teal-100)',
        fg: 'var(--teal-600)',
        bd: 'var(--teal-300)',
    },
    orange: {
        bg: 'var(--orange-100)',
        fg: 'var(--orange-600)',
        bd: 'var(--orange-400)',
    },
    red: { bg: 'var(--red-100)', fg: 'var(--red-600)', bd: 'var(--red-500)' },
    amber: {
        bg: 'var(--amber-100)',
        fg: 'var(--amber-500)',
        bd: 'var(--amber-500)',
    },
    blue: {
        bg: 'var(--blue-100)',
        fg: 'var(--blue-500)',
        bd: 'var(--blue-500)',
    },
};

/* ---------- Badge ---------- */
export function Badge({
    tone = 'neutral',
    children,
    dot = false,
    soft = true,
    style = {},
    icon,
    className = '',
}: {
    tone?: Tone;
    children?: ReactNode;
    dot?: boolean;
    soft?: boolean;
    style?: CSSProperties;
    icon?: string;
    className?: string;
}) {
    const t = TONES[tone] || TONES.neutral;

    return (
        <span
            className={className}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 5,
                height: 20,
                padding: '0 8px',
                fontSize: 11,
                fontWeight: 600,
                lineHeight: 1,
                borderRadius: 999,
                whiteSpace: 'nowrap',
                background: soft ? t.bg : 'transparent',
                color: t.fg,
                border: `1px solid ${soft ? 'transparent' : t.bd}`,
                ...style,
            }}
        >
            {dot && (
                <span
                    style={{
                        width: 6,
                        height: 6,
                        borderRadius: 6,
                        background: t.fg,
                    }}
                />
            )}
            {icon && <Icon name={icon} size={12} strokeWidth={2} />}
            {children}
        </span>
    );
}

/* ---------- status maps ---------- */
export const RUN_STATUS: Record<
    string,
    { tone: Tone; label: string; icon: string }
> = {
    completed: { tone: 'teal', label: 'Completed', icon: 'check2' },
    failed: { tone: 'red', label: 'Failed', icon: 'x' },
    waiting_for_client: {
        tone: 'orange',
        label: 'Waiting for client',
        icon: 'clock',
    },
    running: { tone: 'blue', label: 'Running', icon: 'refresh' },
    requires_tool: { tone: 'orange', label: 'Requires tool', icon: 'tools' },
    expired: { tone: 'amber', label: 'Expired', icon: 'clock' },
    cancelled: { tone: 'neutral', label: 'Cancelled', icon: 'x' },
    queued: { tone: 'neutral', label: 'Queued', icon: 'clock' },
};
export const AGENT_STATUS: Record<string, { tone: Tone; label: string }> = {
    Published: { tone: 'teal', label: 'Published' },
    Testing: { tone: 'blue', label: 'Testing' },
    Draft: { tone: 'neutral', label: 'Draft' },
    Disabled: { tone: 'red', label: 'Disabled' },
};
export const IMPL_STATUS: Record<string, { tone: Tone; label: string }> = {
    ready: { tone: 'teal', label: 'Ready' },
    implemented: { tone: 'teal', label: 'Implemented' },
    required: { tone: 'orange', label: 'Requires implementation' },
    outdated: { tone: 'amber', label: 'Outdated' },
    incompatible: { tone: 'red', label: 'Incompatible' },
    disabled: { tone: 'neutral', label: 'Disabled' },
    'n/a': { tone: 'neutral', label: 'Not required' },
};
export const APP_STATUS: Record<string, { tone: Tone; label: string }> = {
    Active: { tone: 'teal', label: 'Active' },
    Suspended: { tone: 'red', label: 'Suspended' },
    Archived: { tone: 'neutral', label: 'Archived' },
};

export function RunBadge({ status, dot }: { status: string; dot?: boolean }) {
    const m = RUN_STATUS[status] || RUN_STATUS.queued;

    return (
        <Badge tone={m.tone} dot={dot}>
            {m.label}
        </Badge>
    );
}
export function AgentBadge({ status }: { status: string }) {
    const m = AGENT_STATUS[status] || AGENT_STATUS.Draft;

    return (
        <Badge tone={m.tone} dot>
            {m.label}
        </Badge>
    );
}
export function ImplBadge({ status }: { status: string }) {
    const m = IMPL_STATUS[status] || IMPL_STATUS['n/a'];

    return (
        <Badge tone={m.tone} dot>
            {m.label}
        </Badge>
    );
}

export const ENV_TONE: Record<string, Tone> = {
    Production: 'purple',
    Staging: 'blue',
    Development: 'neutral',
};
export function EnvBadge({ env }: { env: string }) {
    return (
        <Badge tone={ENV_TONE[env] || 'neutral'} soft>
            {env}
        </Badge>
    );
}

export const SENS_TONE: Record<string, Tone> = {
    Public: 'teal',
    Internal: 'blue',
    Confidential: 'amber',
    Restricted: 'red',
};
export function SensBadge({ level }: { level: string }) {
    return (
        <Badge tone={SENS_TONE[level] || 'neutral'} soft>
            {level}
        </Badge>
    );
}

export const TOOL_TYPE_META: Record<
    string,
    { label: string; tone: Tone; icon: string }
> = {
    hosted: { label: 'MAAC-hosted', tone: 'purple', icon: 'server' },
    client: { label: 'Client-side', tone: 'orange', icon: 'link' },
    http: { label: 'Remote HTTP', tone: 'blue', icon: 'globe' },
    knowledge: { label: 'Knowledge', tone: 'teal', icon: 'book' },
};

export function ExecChip({ mode }: { mode: string }) {
    const map: Record<string, { tone: Tone; icon: string }> = {
        hosted: { tone: 'purple', icon: 'server' },
        client: { tone: 'orange', icon: 'link' },
        http: { tone: 'blue', icon: 'globe' },
        connector: { tone: 'blue', icon: 'layers' },
        knowledge: { tone: 'teal', icon: 'book' },
        db: { tone: 'amber', icon: 'database' },
    };
    const m = map[mode] || map.hosted;

    return (
        <Badge tone={m.tone} soft>
            {execModeLabel[mode]}
        </Badge>
    );
}

export function scopeBadge(scope: string) {
    const m: Record<string, Tone> = {
        Global: 'purple',
        Project: 'blue',
        Agent: 'teal',
    };

    return (
        <Badge tone={m[scope] || 'neutral'} soft>
            {scope} tool
        </Badge>
    );
}

/* ---------- Button ---------- */
type BtnVariant = 'primary' | 'default' | 'soft' | 'ghost' | 'danger' | 'dark';
type BtnSize = 'sm' | 'md' | 'lg' | 'icon';
export function Btn({
    children,
    variant = 'default',
    size = 'md',
    icon,
    iconRight,
    onClick,
    disabled,
    style = {},
    type = 'button',
    full = false,
}: {
    children?: ReactNode;
    variant?: BtnVariant;
    size?: BtnSize;
    icon?: string;
    iconRight?: string;
    onClick?: () => void;
    disabled?: boolean;
    style?: CSSProperties;
    type?: 'button' | 'submit' | 'reset';
    full?: boolean;
}) {
    const sizes: Record<
        BtnSize,
        { h: number; px: number; fs: number; gap: number; w?: number }
    > = {
        sm: { h: 28, px: 10, fs: 12, gap: 6 },
        md: { h: 34, px: 13, fs: 13, gap: 7 },
        lg: { h: 40, px: 18, fs: 14, gap: 8 },
        icon: { h: 34, px: 0, fs: 13, gap: 0, w: 34 },
    };
    const s = sizes[size] || sizes.md;
    const variants: Record<BtnVariant, CSSProperties & { shadow: string }> = {
        primary: {
            background: 'var(--primary)',
            color: 'var(--primary-contrast)',
            border: '1px solid var(--primary)',
            shadow: 'var(--sh-sm)',
        },
        default: {
            background: 'var(--surface)',
            color: 'var(--text)',
            border: '1px solid var(--border-2)',
            shadow: 'var(--sh-sm)',
        },
        soft: {
            background: 'var(--primary-soft)',
            color: 'var(--primary)',
            border: '1px solid transparent',
            shadow: 'none',
        },
        ghost: {
            background: 'transparent',
            color: 'var(--text-2)',
            border: '1px solid transparent',
            shadow: 'none',
        },
        danger: {
            background: 'var(--surface)',
            color: 'var(--red-600)',
            border: '1px solid var(--red-500)',
            shadow: 'none',
        },
        dark: {
            background: 'var(--navy-900)',
            color: '#fff',
            border: '1px solid var(--navy-900)',
            shadow: 'var(--sh-sm)',
        },
    };
    const { shadow, ...v } = variants[variant] || variants.default;

    return (
        <button
            type={type}
            onClick={disabled ? undefined : onClick}
            disabled={disabled}
            className="maac-btn"
            style={{
                display: full ? 'flex' : 'inline-flex',
                width: full ? '100%' : s.w || 'auto',
                alignItems: 'center',
                justifyContent: 'center',
                gap: s.gap,
                height: s.h,
                padding: s.w ? 0 : `0 ${s.px}px`,
                fontSize: s.fs,
                fontWeight: 600,
                borderRadius: 'var(--r-sm)',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
                whiteSpace: 'nowrap',
                transition: 'filter .12s, transform .04s, background .12s',
                ...v,
                boxShadow: shadow,
                ...style,
            }}
        >
            {icon && <Icon name={icon} size={s.fs + 2} strokeWidth={2} />}
            {children}
            {iconRight && (
                <Icon name={iconRight} size={s.fs + 2} strokeWidth={2} />
            )}
        </button>
    );
}

/* ---------- Card ---------- */
export function Card({
    children,
    style = {},
    pad = true,
    hover = false,
    onClick,
    className = '',
}: {
    children?: ReactNode;
    style?: CSSProperties;
    pad?: boolean;
    hover?: boolean;
    onClick?: () => void;
    className?: string;
}) {
    return (
        <div
            onClick={onClick}
            className={className + (hover ? ' maac-card-hover' : '')}
            style={{
                background: 'var(--surface)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-lg)',
                boxShadow: 'var(--sh-sm)',
                padding: pad ? 16 : 0,
                cursor: onClick ? 'pointer' : 'default',
                transition:
                    'box-shadow .15s, border-color .15s, transform .12s',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

/* ---------- Section header ---------- */
export function SectionHeader({
    title,
    sub,
    right,
    icon,
    style = {},
}: {
    title: ReactNode;
    sub?: ReactNode;
    right?: ReactNode;
    icon?: string;
    style?: CSSProperties;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 12,
                marginBottom: 12,
                ...style,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    minWidth: 0,
                }}
            >
                {icon && (
                    <span style={{ color: 'var(--primary)', display: 'flex' }}>
                        <Icon name={icon} size={17} />
                    </span>
                )}
                <div style={{ minWidth: 0 }}>
                    <div
                        style={{
                            fontSize: 14,
                            fontWeight: 700,
                            letterSpacing: -0.1,
                        }}
                    >
                        {title}
                    </div>
                    {sub && (
                        <div
                            style={{
                                fontSize: 12,
                                color: 'var(--text-3)',
                                marginTop: 1,
                            }}
                        >
                            {sub}
                        </div>
                    )}
                </div>
            </div>
            {right && (
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        alignItems: 'center',
                        flexShrink: 0,
                    }}
                >
                    {right}
                </div>
            )}
        </div>
    );
}

/* ---------- Page header ---------- */
export type Crumb = { label: ReactNode; onClick?: () => void };
export function PageHeader({
    title,
    sub,
    breadcrumb,
    actions,
    tabs,
    badge,
}: {
    title: ReactNode;
    sub?: ReactNode;
    breadcrumb?: Crumb[];
    actions?: ReactNode;
    tabs?: ReactNode;
    badge?: ReactNode;
}) {
    return (
        <div style={{ marginBottom: 18 }}>
            {breadcrumb && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                        fontSize: 12,
                        color: 'var(--text-3)',
                        marginBottom: 9,
                        flexWrap: 'wrap',
                    }}
                >
                    {breadcrumb.map((b, i) => (
                        <span
                            key={i}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                            }}
                        >
                            {i > 0 && (
                                <Icon
                                    name="chevright"
                                    size={12}
                                    style={{ opacity: 0.6 }}
                                />
                            )}
                            <span
                                onClick={b.onClick}
                                className={b.onClick ? 'maac-link' : ''}
                                style={{
                                    cursor: b.onClick ? 'pointer' : 'default',
                                    color:
                                        i === breadcrumb.length - 1
                                            ? 'var(--text-2)'
                                            : 'var(--text-3)',
                                    fontWeight:
                                        i === breadcrumb.length - 1 ? 600 : 400,
                                }}
                            >
                                {b.label}
                            </span>
                        </span>
                    ))}
                </div>
            )}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    justifyContent: 'space-between',
                    gap: 16,
                    flexWrap: 'wrap',
                }}
            >
                <div style={{ minWidth: 0 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 11,
                        }}
                    >
                        <h1
                            style={{
                                margin: 0,
                                fontSize: 23,
                                fontWeight: 700,
                                letterSpacing: -0.4,
                            }}
                        >
                            {title}
                        </h1>
                        {badge}
                    </div>
                    {sub && (
                        <div
                            style={{
                                fontSize: 13.5,
                                color: 'var(--text-2)',
                                marginTop: 5,
                                maxWidth: 760,
                            }}
                        >
                            {sub}
                        </div>
                    )}
                </div>
                {actions && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 9,
                            alignItems: 'center',
                            flexShrink: 0,
                        }}
                    >
                        {actions}
                    </div>
                )}
            </div>
            {tabs}
        </div>
    );
}

/* ---------- Tabs ---------- */
export type TabDef = {
    id: string;
    label: string;
    icon?: string;
    count?: number;
};
export function Tabs({
    tabs,
    active,
    onChange,
    style = {},
}: {
    tabs: TabDef[];
    active: string;
    onChange: (id: string) => void;
    style?: CSSProperties;
}) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 2,
                borderBottom: '1px solid var(--border)',
                marginTop: 16,
                overflowX: 'auto',
                ...style,
            }}
        >
            {tabs.map((t) => {
                const on = t.id === active;

                return (
                    <button
                        key={t.id}
                        onClick={() => onChange(t.id)}
                        style={{
                            position: 'relative',
                            border: 'none',
                            background: 'none',
                            cursor: 'pointer',
                            padding: '9px 13px 11px',
                            fontSize: 13,
                            fontWeight: on ? 600 : 500,
                            color: on ? 'var(--primary)' : 'var(--text-2)',
                            whiteSpace: 'nowrap',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            transition: 'color .12s',
                        }}
                    >
                        {t.icon && <Icon name={t.icon} size={15} />}
                        {t.label}
                        {t.count != null && (
                            <span
                                style={{
                                    fontSize: 11,
                                    fontWeight: 600,
                                    padding: '1px 6px',
                                    borderRadius: 999,
                                    background: on
                                        ? 'var(--primary-soft)'
                                        : 'var(--surface-3)',
                                    color: on
                                        ? 'var(--primary)'
                                        : 'var(--text-3)',
                                }}
                            >
                                {t.count}
                            </span>
                        )}
                        {on && (
                            <span
                                style={{
                                    position: 'absolute',
                                    left: 8,
                                    right: 8,
                                    bottom: -1,
                                    height: 2,
                                    background: 'var(--primary)',
                                    borderRadius: 2,
                                }}
                            />
                        )}
                    </button>
                );
            })}
        </div>
    );
}

/* ---------- Segmented ---------- */
type SegOption = { value: string; label?: string; icon?: string } | string;
export function Segmented({
    options,
    value,
    onChange,
    size = 'md',
}: {
    options: SegOption[];
    value: string;
    onChange: (v: string) => void;
    size?: 'sm' | 'md';
}) {
    const h = size === 'sm' ? 28 : 32;

    return (
        <div
            style={{
                display: 'inline-flex',
                background: 'var(--surface-3)',
                borderRadius: 'var(--r-sm)',
                padding: 3,
                gap: 2,
            }}
        >
            {options.map((o) => {
                const val = typeof o === 'object' ? o.value : o;
                const on = val === value;

                return (
                    <button
                        key={val}
                        onClick={() => onChange(val)}
                        style={{
                            height: h - 6,
                            padding: '0 11px',
                            border: 'none',
                            borderRadius: 'var(--r-xs)',
                            cursor: 'pointer',
                            fontSize: 12.5,
                            fontWeight: 600,
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            background: on ? 'var(--surface)' : 'transparent',
                            color: on ? 'var(--text)' : 'var(--text-3)',
                            boxShadow: on ? 'var(--sh-sm)' : 'none',
                            transition: 'all .12s',
                        }}
                    >
                        {typeof o === 'object' && o.icon && (
                            <Icon name={o.icon} size={14} />
                        )}
                        {typeof o === 'object' ? o.label : o}
                    </button>
                );
            })}
        </div>
    );
}

/* ---------- Table ---------- */
export type Column = {
    label: string;
    align?: 'left' | 'center' | 'right';
    width?: number | string;
};
export function Table({
    columns,
    children,
    style = {},
}: {
    columns: Column[];
    children: ReactNode;
    style?: CSSProperties;
}) {
    return (
        <div
            style={{
                overflowX: 'auto',
                borderRadius: 'var(--r-lg)',
                border: '1px solid var(--border)',
                background: 'var(--surface)',
                boxShadow: 'var(--sh-sm)',
                ...style,
            }}
        >
            <table
                style={{
                    width: '100%',
                    borderCollapse: 'collapse',
                    fontSize: 12.5,
                }}
            >
                <thead>
                    <tr style={{ background: 'var(--surface-2)' }}>
                        {columns.map((c, i) => (
                            <th
                                key={i}
                                style={{
                                    textAlign: c.align || 'left',
                                    padding: '9px 14px',
                                    fontSize: 11,
                                    fontWeight: 600,
                                    color: 'var(--text-3)',
                                    textTransform: 'uppercase',
                                    letterSpacing: 0.4,
                                    borderBottom: '1px solid var(--border)',
                                    whiteSpace: 'nowrap',
                                    width: c.width,
                                }}
                            >
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}
export function Tr({
    children,
    onClick,
    hover = true,
}: {
    children: ReactNode;
    onClick?: () => void;
    hover?: boolean;
}) {
    return (
        <tr
            onClick={onClick}
            className={onClick && hover ? 'maac-row' : ''}
            style={{
                cursor: onClick ? 'pointer' : 'default',
                transition: 'background .1s',
            }}
        >
            {children}
        </tr>
    );
}
export function Td({
    children,
    align = 'left',
    style = {},
    mono = false,
    strong = false,
}: {
    children?: ReactNode;
    align?: 'left' | 'center' | 'right';
    style?: CSSProperties;
    mono?: boolean;
    strong?: boolean;
}) {
    return (
        <td
            style={{
                padding: '11px 14px',
                borderBottom: '1px solid var(--border)',
                textAlign: align,
                color: strong ? 'var(--text)' : 'var(--text-2)',
                fontWeight: strong ? 600 : 400,
                fontFamily: mono ? 'var(--mono)' : 'inherit',
                verticalAlign: 'middle',
                ...style,
            }}
        >
            {children}
        </td>
    );
}

/* ---------- Field / Input ---------- */
export function Field({
    label,
    children,
    hint,
    required,
    style = {},
}: {
    label?: ReactNode;
    children: ReactNode;
    hint?: ReactNode;
    required?: boolean;
    style?: CSSProperties;
}) {
    return (
        <label style={{ display: 'block', ...style }}>
            {label && (
                <div
                    style={{
                        fontSize: 12,
                        fontWeight: 600,
                        color: 'var(--text-2)',
                        marginBottom: 6,
                    }}
                >
                    {label}
                    {required && (
                        <span style={{ color: 'var(--orange-600)' }}> *</span>
                    )}
                </div>
            )}
            {children}
            {hint && (
                <div
                    style={{
                        fontSize: 11.5,
                        color: 'var(--text-3)',
                        marginTop: 5,
                    }}
                >
                    {hint}
                </div>
            )}
        </label>
    );
}

export const inputStyle: CSSProperties = {
    width: '100%',
    height: 36,
    padding: '0 11px',
    fontSize: 13,
    color: 'var(--text)',
    background: 'var(--surface)',
    border: '1px solid var(--border-2)',
    borderRadius: 'var(--r-sm)',
    outline: 'none',
    transition: 'border-color .12s, box-shadow .12s',
    fontFamily: 'inherit',
};

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    const { style, ...rest } = props;

    return (
        <input
            {...rest}
            className="maac-input"
            style={{ ...inputStyle, ...(style || {}) }}
        />
    );
}

export function Textarea(
    props: React.TextareaHTMLAttributes<HTMLTextAreaElement>,
) {
    const { style, ...rest } = props;

    return (
        <textarea
            {...rest}
            className="maac-input"
            style={{
                ...inputStyle,
                height: 'auto',
                padding: '9px 11px',
                lineHeight: 1.55,
                resize: 'vertical',
                ...(style || {}),
            }}
        />
    );
}

/* ---------- Select (shadcn Radix under the hood) ---------- */
type SelectOption = { value: string; label?: string } | string;
export function Select({
    value,
    onChange,
    options,
    style = {},
}: {
    value: string;
    onChange: (v: string) => void;
    options: SelectOption[];
    style?: CSSProperties;
}) {
    const opts = options.map((o) =>
        typeof o === 'object' ? o : { value: o, label: o },
    );

    return (
        <div style={{ position: 'relative', ...style }}>
            <ShadSelect value={value} onValueChange={onChange}>
                <SelectTrigger
                    className="maac-input maac-select-trigger"
                    style={{
                        ...inputStyle,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        cursor: 'pointer',
                        width: '100%',
                    }}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent
                    // Radix portals the dropdown to <body>, outside the
                    // `.maac-theme` scope — re-apply it (as Modal does) so the
                    // Milaha tokens resolve and the panel isn't transparent.
                    className="maac-theme"
                    style={{
                        background: 'var(--surface)',
                        border: '1px solid var(--border)',
                        color: 'var(--text)',
                        borderRadius: 'var(--r-md)',
                        boxShadow: 'var(--sh-pop)',
                    }}
                >
                    {opts.map((o) => (
                        <SelectItem
                            key={o.value}
                            value={o.value}
                            style={{
                                fontSize: 13,
                                color: 'var(--text)',
                                cursor: 'pointer',
                            }}
                        >
                            {o.label ?? o.value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </ShadSelect>
        </div>
    );
}

/* ---------- Toggle ---------- */
export function Toggle({
    on,
    onChange,
    size = 'md',
}: {
    on: boolean;
    onChange: (v: boolean) => void;
    size?: 'sm' | 'md';
}) {
    const w = size === 'sm' ? 32 : 38;
    const h = size === 'sm' ? 18 : 22;
    const k = h - 4;

    return (
        <button
            onClick={() => onChange(!on)}
            style={{
                width: w,
                height: h,
                borderRadius: 999,
                border: 'none',
                cursor: 'pointer',
                padding: 0,
                background: on ? 'var(--primary)' : 'var(--border-2)',
                position: 'relative',
                transition: 'background .15s',
                flexShrink: 0,
            }}
        >
            <span
                style={{
                    position: 'absolute',
                    top: 2,
                    left: on ? w - k - 2 : 2,
                    width: k,
                    height: k,
                    borderRadius: 999,
                    background: '#fff',
                    boxShadow: '0 1px 3px rgba(0,0,0,.3)',
                    transition: 'left .16s cubic-bezier(.4,0,.2,1)',
                }}
            />
        </button>
    );
}

/* ---------- Avatar ---------- */
export function Avatar({
    name,
    size = 30,
    tone,
}: {
    name: string;
    size?: number;
    tone?: string;
}) {
    const initials = (name || '?')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
    const tones = [
        'var(--purple-600)',
        'var(--teal-500)',
        'var(--navy-700)',
        'var(--orange-600)',
        'var(--blue-500)',
    ];
    const c = tone || tones[(name || '').length % tones.length];

    return (
        <div
            style={{
                width: size,
                height: size,
                borderRadius: '30%',
                background: c,
                color: '#fff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: size * 0.38,
                fontWeight: 700,
                flexShrink: 0,
                letterSpacing: 0.2,
            }}
        >
            {initials}
        </div>
    );
}

/* ---------- App mark ---------- */
export function AppMark({ code, size = 34 }: { code: string; size?: number }) {
    const colors: Record<string, string> = {
        MOP: 'var(--purple-600)',
        FWS: 'var(--teal-500)',
        PMA: 'var(--orange-600)',
        CSP: 'var(--blue-500)',
        VMS: 'var(--navy-700)',
    };

    return (
        <div
            style={{
                width: size,
                height: size,
                borderRadius: 8,
                background: colors[code] || 'var(--purple-600)',
                color: '#fff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: size * 0.34,
                fontWeight: 700,
                flexShrink: 0,
                fontFamily: 'var(--mono)',
            }}
        >
            {code}
        </div>
    );
}

/* ---------- Empty state ---------- */
export function EmptyState({
    icon = 'layers',
    title,
    desc,
    action,
}: {
    icon?: string;
    title: ReactNode;
    desc?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '54px 20px',
                textAlign: 'center',
            }}
        >
            <div
                style={{
                    width: 50,
                    height: 50,
                    borderRadius: 'var(--r-lg)',
                    background: 'var(--primary-soft)',
                    color: 'var(--primary)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 14,
                }}
            >
                <Icon name={icon} size={24} />
            </div>
            <div style={{ fontSize: 15, fontWeight: 700 }}>{title}</div>
            {desc && (
                <div
                    style={{
                        fontSize: 13,
                        color: 'var(--text-3)',
                        marginTop: 5,
                        maxWidth: 380,
                    }}
                >
                    {desc}
                </div>
            )}
            {action && <div style={{ marginTop: 16 }}>{action}</div>}
        </div>
    );
}

/* ---------- Code block with copy ---------- */
export function CodeBlock({
    code,
    lang = '',
    style = {},
    maxHeight,
    copyable = true,
}: {
    code: string;
    lang?: string;
    style?: CSSProperties;
    maxHeight?: number;
    copyable?: boolean;
}) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard?.writeText(code);
        setCopied(true);
        setTimeout(() => setCopied(false), 1400);
    };

    return (
        <div
            style={{
                position: 'relative',
                background: 'var(--code-bg)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-md)',
                overflow: 'hidden',
                ...style,
            }}
        >
            {lang && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '6px 12px',
                        borderBottom: '1px solid var(--border)',
                        background: 'var(--surface-2)',
                    }}
                >
                    <span
                        style={{
                            fontSize: 11,
                            fontWeight: 600,
                            color: 'var(--text-3)',
                            fontFamily: 'var(--mono)',
                            textTransform: 'lowercase',
                        }}
                    >
                        {lang}
                    </span>
                    {copyable && (
                        <button
                            onClick={copy}
                            className="maac-link"
                            style={{
                                border: 'none',
                                background: 'none',
                                cursor: 'pointer',
                                fontSize: 11,
                                fontWeight: 600,
                                color: copied
                                    ? 'var(--teal-600)'
                                    : 'var(--text-3)',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 5,
                                padding: 0,
                            }}
                        >
                            <Icon name={copied ? 'check' : 'copy'} size={13} />
                            {copied ? 'Copied' : 'Copy'}
                        </button>
                    )}
                </div>
            )}
            <pre
                style={{
                    margin: 0,
                    padding: '13px 14px',
                    overflow: 'auto',
                    maxHeight,
                    fontFamily: 'var(--mono)',
                    fontSize: 12,
                    lineHeight: 1.65,
                    color: 'var(--code-text)',
                    whiteSpace: 'pre',
                }}
            >
                <code>{code}</code>
            </pre>
            {!lang && copyable && (
                <button
                    onClick={copy}
                    className="maac-copybtn"
                    style={{
                        position: 'absolute',
                        top: 9,
                        right: 9,
                        border: '1px solid var(--border-2)',
                        background: 'var(--surface)',
                        cursor: 'pointer',
                        fontSize: 11,
                        fontWeight: 600,
                        color: copied ? 'var(--teal-600)' : 'var(--text-2)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 5,
                        padding: '4px 8px',
                        borderRadius: 'var(--r-xs)',
                    }}
                >
                    <Icon name={copied ? 'check' : 'copy'} size={12} />
                    {copied ? 'Copied' : 'Copy'}
                </button>
            )}
        </div>
    );
}

/* ---------- Key-value rows ---------- */
export type KVItem = {
    k: ReactNode;
    v: ReactNode;
    mono?: boolean;
    strong?: boolean;
};
export function KV({
    items,
    cols = 2,
    style = {},
}: {
    items: KVItem[];
    cols?: number;
    style?: CSSProperties;
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: `repeat(${cols}, 1fr)`,
                gap: '14px 24px',
                ...style,
            }}
        >
            {items.map((it, i) => (
                <div key={i} style={{ minWidth: 0 }}>
                    <div
                        style={{
                            fontSize: 11,
                            color: 'var(--text-3)',
                            fontWeight: 600,
                            textTransform: 'uppercase',
                            letterSpacing: 0.3,
                            marginBottom: 3,
                        }}
                    >
                        {it.k}
                    </div>
                    <div
                        style={{
                            fontSize: 13,
                            color: 'var(--text)',
                            fontWeight: it.strong ? 600 : 500,
                            fontFamily: it.mono ? 'var(--mono)' : 'inherit',
                            wordBreak: 'break-word',
                        }}
                    >
                        {it.v}
                    </div>
                </div>
            ))}
        </div>
    );
}

/* ---------- Modal (shadcn Dialog under the hood) ---------- */
export function Modal({
    open,
    onClose,
    title,
    sub,
    children,
    footer,
    width = 560,
    icon,
}: {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    sub?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    width?: number;
    icon?: string;
}) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent
                showCloseButton={false}
                className="maac-theme top-[7vh] translate-y-0 grid-cols-1 gap-0 overflow-hidden border p-0"
                style={{
                    width,
                    maxWidth: 'calc(100% - 40px)',
                    maxHeight: '86vh',
                    background: 'var(--surface)',
                    borderColor: 'var(--border)',
                    borderRadius: 'var(--r-xl)',
                    boxShadow: 'var(--sh-lg)',
                    display: 'flex',
                    flexDirection: 'column',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 12,
                        padding: '18px 20px 14px',
                        borderBottom: '1px solid var(--border)',
                    }}
                >
                    {icon && (
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 'var(--r-md)',
                                background: 'var(--primary-soft)',
                                color: 'var(--primary)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name={icon} size={19} />
                        </div>
                    )}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <DialogTitle
                            style={{
                                fontSize: 16,
                                fontWeight: 700,
                                color: 'var(--text)',
                            }}
                        >
                            {title}
                        </DialogTitle>
                        {sub ? (
                            <DialogDescription
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                    marginTop: 2,
                                }}
                            >
                                {sub}
                            </DialogDescription>
                        ) : (
                            <DialogDescription className="sr-only">
                                {typeof title === 'string' ? title : 'Dialog'}
                            </DialogDescription>
                        )}
                    </div>
                    <button
                        onClick={onClose}
                        className="maac-iconbtn"
                        style={{
                            border: 'none',
                            background: 'none',
                            cursor: 'pointer',
                            color: 'var(--text-3)',
                            padding: 4,
                            display: 'flex',
                            borderRadius: 6,
                        }}
                    >
                        <Icon name="x" size={18} />
                    </button>
                </div>
                <div style={{ padding: '18px 20px', overflowY: 'auto' }}>
                    {children}
                </div>
                {footer && (
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 9,
                            padding: '14px 20px',
                            borderTop: '1px solid var(--border)',
                            background: 'var(--surface-2)',
                            borderRadius: '0 0 var(--r-xl) var(--r-xl)',
                        }}
                    >
                        {footer}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

/* ---------- Tooltip dot ---------- */
export function InfoDot({ text }: { text: string }) {
    return (
        <span
            className="maac-tip"
            data-tip={text}
            style={{
                display: 'inline-flex',
                color: 'var(--text-3)',
                cursor: 'help',
                verticalAlign: 'middle',
            }}
        >
            <Icon name="info" size={13} />
        </span>
    );
}
