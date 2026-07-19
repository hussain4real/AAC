/* ============================================================
   MAACC — Charts (pure SVG/CSS, no deps)
   Ported from the prototype's charts.jsx.
   ============================================================ */
import { useEffect, useId, useState } from 'react';
import { Icon } from '@/maacc/icons';
import { Card } from './ui';
import { TONES } from './ui';
import type { Tone } from './ui';

export type SeriesPoint = { label: string; value: number; color?: string };

/* ---------- Donut ---------- */
export function Donut({
    data,
    size = 150,
    thickness = 18,
    centerLabel,
    centerSub,
}: {
    data: SeriesPoint[];
    size?: number;
    thickness?: number;
    centerLabel?: string | number;
    centerSub?: string;
}) {
    const total = data.reduce((s, d) => s + d.value, 0) || 1;
    const r = (size - thickness) / 2;
    const c = 2 * Math.PI * r;
    const [mounted, setMounted] = useState(false);
    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 40);

        return () => clearTimeout(t);
    }, []);

    // Cumulative arc offset per segment, precomputed (no render-time mutation).
    const offsets: number[] = [];
    data.reduce((acc, d) => {
        offsets.push(acc);

        return acc + (d.value / total) * c;
    }, 0);

    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
            <circle
                cx={size / 2}
                cy={size / 2}
                r={r}
                fill="none"
                stroke="var(--track)"
                strokeWidth={thickness}
            />
            {data.map((d, i) => {
                const frac = d.value / total;
                const len = frac * c;
                const dash = `${mounted ? len : 0} ${c}`;

                return (
                    <circle
                        key={i}
                        cx={size / 2}
                        cy={size / 2}
                        r={r}
                        fill="none"
                        stroke={d.color}
                        strokeWidth={thickness}
                        strokeDasharray={dash}
                        strokeDashoffset={-offsets[i]}
                        strokeLinecap="butt"
                        transform={`rotate(-90 ${size / 2} ${size / 2})`}
                        style={{
                            transition:
                                'stroke-dasharray .9s cubic-bezier(.3,.9,.3,1)',
                        }}
                    />
                );
            })}
            {centerLabel != null && (
                <g>
                    <text
                        x="50%"
                        y="48%"
                        textAnchor="middle"
                        dominantBaseline="middle"
                        className="tnum"
                        style={{
                            fontSize: size * 0.19,
                            fontWeight: 700,
                            fill: 'var(--text)',
                            fontFamily: 'var(--font)',
                        }}
                    >
                        {centerLabel}
                    </text>
                    {centerSub && (
                        <text
                            x="50%"
                            y="63%"
                            textAnchor="middle"
                            dominantBaseline="middle"
                            style={{
                                fontSize: size * 0.075,
                                fontWeight: 600,
                                fill: 'var(--text-3)',
                                fontFamily: 'var(--font)',
                                textTransform: 'uppercase',
                                letterSpacing: 0.5,
                            }}
                        >
                            {centerSub}
                        </text>
                    )}
                </g>
            )}
        </svg>
    );
}

export function DonutLegend({
    data,
    total,
}: {
    data: SeriesPoint[];
    total?: number;
}) {
    const sum = total ?? data.reduce((s, d) => s + d.value, 0);

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 9,
                flex: 1,
                minWidth: 0,
            }}
        >
            {data.map((d, i) => (
                <div
                    key={i}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                        fontSize: 12.5,
                    }}
                >
                    <span
                        style={{
                            width: 9,
                            height: 9,
                            borderRadius: 3,
                            background: d.color,
                            flexShrink: 0,
                        }}
                    />
                    <span
                        style={{
                            color: 'var(--text-2)',
                            flex: 1,
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {d.label}
                    </span>
                    <span
                        className="tnum"
                        style={{ fontWeight: 700, color: 'var(--text)' }}
                    >
                        {d.value.toLocaleString()}
                    </span>
                    <span
                        className="tnum"
                        style={{
                            color: 'var(--text-3)',
                            width: 38,
                            textAlign: 'right',
                            fontSize: 11.5,
                        }}
                    >
                        {((d.value / (sum || 1)) * 100).toFixed(0)}%
                    </span>
                </div>
            ))}
        </div>
    );
}

/* ---------- Horizontal bars ---------- */
export function HBars({
    data,
    max,
    valueFmt = (v: number) => v.toLocaleString(),
    barColor = 'var(--primary)',
    onClick,
}: {
    data: SeriesPoint[];
    max?: number;
    valueFmt?: (v: number) => string;
    barColor?: string;
    onClick?: (d: SeriesPoint) => void;
}) {
    const m = max ?? Math.max(...data.map((d) => d.value), 1);
    const [mounted, setMounted] = useState(false);
    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 50);

        return () => clearTimeout(t);
    }, []);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 13 }}>
            {data.map((d, i) => (
                <div
                    key={i}
                    onClick={onClick ? () => onClick(d) : undefined}
                    style={{ cursor: onClick ? 'pointer' : 'default' }}
                    className={onClick ? 'maacc-hbar' : ''}
                >
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'baseline',
                            marginBottom: 5,
                            gap: 10,
                        }}
                    >
                        <span
                            style={{
                                fontSize: 12.5,
                                fontWeight: 500,
                                color: 'var(--text-2)',
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {d.label}
                        </span>
                        <span
                            className="tnum"
                            style={{
                                fontSize: 12.5,
                                fontWeight: 700,
                                color: 'var(--text)',
                                flexShrink: 0,
                            }}
                        >
                            {valueFmt(d.value)}
                        </span>
                    </div>
                    <div
                        style={{
                            height: 7,
                            background: 'var(--track)',
                            borderRadius: 999,
                            overflow: 'hidden',
                        }}
                    >
                        <div
                            style={{
                                height: '100%',
                                width: mounted ? `${(d.value / m) * 100}%` : 0,
                                background: d.color || barColor,
                                borderRadius: 999,
                                transition: `width .8s cubic-bezier(.3,.9,.3,1) ${i * 60}ms`,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

/* ---------- Vertical bars (column) ---------- */
export function VBars({
    data,
    height = 120,
    color = 'var(--primary)',
    labels = true,
}: {
    data: SeriesPoint[];
    height?: number;
    color?: string;
    labels?: boolean;
}) {
    const max = Math.max(...data.map((d) => d.value), 1);
    const [mounted, setMounted] = useState(false);
    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 50);

        return () => clearTimeout(t);
    }, []);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-end',
                gap: 6,
                height,
                width: '100%',
            }}
        >
            {data.map((d, i) => (
                <div
                    key={i}
                    className="maacc-vbar"
                    data-tip={`${d.label}: ${d.value.toLocaleString()}`}
                    style={{
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'flex-end',
                        height: '100%',
                        gap: 6,
                    }}
                >
                    <div
                        style={{
                            width: '100%',
                            maxWidth: 26,
                            height: mounted ? `${(d.value / max) * 100}%` : 0,
                            minHeight: 3,
                            background: d.color || color,
                            borderRadius: '4px 4px 2px 2px',
                            transition: `height .7s cubic-bezier(.3,.9,.3,1) ${i * 40}ms`,
                            opacity: 0.92,
                        }}
                    />
                    {labels && (
                        <span
                            style={{
                                fontSize: 10,
                                color: 'var(--text-3)',
                                fontWeight: 500,
                            }}
                        >
                            {d.label}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}

/* ---------- Area sparkline ---------- */
export function AreaSpark({
    values,
    width = 600,
    height = 110,
    color = 'var(--primary)',
}: {
    values: number[];
    width?: number;
    height?: number;
    color?: string;
}) {
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const pts = values.map((v, i) => {
        const x =
            values.length === 1 ? width / 2 : (i / (values.length - 1)) * width;
        const y = height - ((v - min) / range) * (height - 12) - 6;

        return [x, y] as const;
    });
    const line = pts
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`,
        )
        .join(' ');
    const area =
        pts.length > 1 ? `${line} L${width} ${height} L0 ${height} Z` : '';
    const gid = 'ag' + useId().replace(/[:]/g, '');
    const [dash, setDash] = useState(true);
    useEffect(() => {
        const t = setTimeout(() => setDash(false), 0);

        return () => clearTimeout(t);
    }, []);

    return (
        <svg
            role="img"
            aria-label={
                values.length === 0
                    ? 'No trend data'
                    : `Trend with ${values.length} data points`
            }
            width="100%"
            viewBox={`0 0 ${width} ${height}`}
            preserveAspectRatio="none"
            style={{ display: 'block' }}
        >
            <defs>
                <linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={color} stopOpacity="0.22" />
                    <stop offset="100%" stopColor={color} stopOpacity="0" />
                </linearGradient>
            </defs>
            {area && (
                <path
                    d={area}
                    fill={`url(#${gid})`}
                    style={{
                        opacity: dash ? 0 : 1,
                        transition: 'opacity .6s ease .3s',
                    }}
                />
            )}
            {line && (
                <path
                    d={line}
                    fill="none"
                    stroke={color}
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    style={{
                        strokeDasharray: 2000,
                        strokeDashoffset: dash ? 2000 : 0,
                        transition: 'stroke-dashoffset 1.1s ease',
                    }}
                />
            )}
            {pts.length > 0 && (
                <circle
                    cx={pts[pts.length - 1][0]}
                    cy={pts[pts.length - 1][1]}
                    r="3.5"
                    fill={color}
                    style={{
                        opacity: dash ? 0 : 1,
                        transition: 'opacity .3s ease 1s',
                    }}
                />
            )}
        </svg>
    );
}

/* ---------- Mini sparkline ---------- */
export function MiniSpark({
    values,
    width = 88,
    height = 28,
    color = 'var(--teal-500)',
}: {
    values: number[];
    width?: number;
    height?: number;
    color?: string;
}) {
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const line = values
        .map((v, i) => {
            const x =
                values.length === 1
                    ? width / 2
                    : (i / (values.length - 1)) * width;
            const y = height - ((v - min) / range) * (height - 4) - 2;

            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');

    return (
        <svg
            role="img"
            aria-label={
                values.length === 0
                    ? 'No trend data'
                    : `Trend with ${values.length} data points`
            }
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
        >
            {line && (
                <path
                    d={line}
                    fill="none"
                    stroke={color}
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}
            {values.length === 1 && (
                <circle cx={width / 2} cy={height / 2} r="2.5" fill={color} />
            )}
        </svg>
    );
}

/* ---------- Progress bar ---------- */
export function Progress({
    value,
    max = 100,
    color = 'var(--primary)',
    height = 7,
    showVal = false,
    track = 'var(--track)',
}: {
    value: number;
    max?: number;
    color?: string;
    height?: number;
    showVal?: boolean;
    track?: string;
}) {
    const pct = Math.min(100, (value / max) * 100);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                width: '100%',
            }}
        >
            <div
                style={{
                    flex: 1,
                    height,
                    background: track,
                    borderRadius: 999,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${pct}%`,
                        background: color,
                        borderRadius: 999,
                        transition: 'width .6s cubic-bezier(.3,.9,.3,1)',
                    }}
                />
            </div>
            {showVal && (
                <span
                    className="tnum"
                    style={{
                        fontSize: 11.5,
                        fontWeight: 700,
                        color: 'var(--text-2)',
                        minWidth: 32,
                        textAlign: 'right',
                    }}
                >
                    {pct.toFixed(0)}%
                </span>
            )}
        </div>
    );
}

/* ---------- Stat card ---------- */
export function StatCard({
    label,
    value,
    icon,
    tone = 'purple',
    delta,
    deltaTone,
    spark,
    sub,
    onClick,
}: {
    label: string;
    value: string | number;
    icon?: string;
    tone?: Tone;
    delta?: string;
    deltaTone?: 'up' | 'down';
    spark?: number[];
    sub?: string;
    onClick?: () => void;
}) {
    const t = TONES[tone] || TONES.purple;

    return (
        <Card
            hover={!!onClick}
            onClick={onClick}
            style={{
                padding: 14,
                display: 'flex',
                flexDirection: 'column',
                gap: 9,
                minWidth: 0,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 8,
                }}
            >
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        color: 'var(--text-3)',
                        textTransform: 'uppercase',
                        letterSpacing: 0.3,
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                    }}
                >
                    {label}
                </span>
                {icon && (
                    <span
                        style={{
                            width: 26,
                            height: 26,
                            borderRadius: 7,
                            background: t.bg,
                            color: t.fg,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            flexShrink: 0,
                        }}
                    >
                        <Icon name={icon} size={15} />
                    </span>
                )}
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                <span
                    className="tnum"
                    style={{
                        fontSize: 26,
                        fontWeight: 700,
                        letterSpacing: -0.6,
                        lineHeight: 1,
                    }}
                >
                    {value}
                </span>
                {delta && (
                    <span
                        style={{
                            fontSize: 11.5,
                            fontWeight: 700,
                            color:
                                deltaTone === 'down'
                                    ? 'var(--red-500)'
                                    : 'var(--teal-600)',
                        }}
                    >
                        {delta}
                    </span>
                )}
            </div>
            {sub && (
                <div style={{ fontSize: 11.5, color: 'var(--text-3)' }}>
                    {sub}
                </div>
            )}
            {spark && (
                <div style={{ marginTop: 2 }}>
                    <MiniSpark
                        values={spark}
                        width={200}
                        height={26}
                        color={t.fg}
                    />
                </div>
            )}
        </Card>
    );
}

export type { Tone };
