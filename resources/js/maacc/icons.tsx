/* ============================================================
   MAACC — Icon set (simple stroke paths, 24x24 viewbox)
   Ported from the handoff prototype so screens match exactly.
   ============================================================ */
import type { CSSProperties } from 'react';

export const ICON_PATHS: Record<string, string> = {
    // nav
    dashboard: 'M3 13h7V4H3v9Zm0 7h7v-5H3v5Zm11 0h7V11h-7v9Zm0-16v5h7V4h-7Z',
    apps: 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V5Zm0 5h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9Zm3-4h.01M10 6h.01',
    projects:
        'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z',
    agents: 'M12 3a3 3 0 0 1 3 3v1h1a3 3 0 0 1 3 3v1m-10-8a3 3 0 0 0-3 3v1H5a3 3 0 0 0-3 3v1m7 8v-3m4 3v-3M8 21h8M9 11h.01M15 11h.01M8 7h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z',
    tools: 'M14.7 6.3a4 4 0 0 1-5.4 5.4l-5.6 5.6a2 2 0 1 0 2.8 2.8l5.6-5.6a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-.4-.4-2.1 2.2-2.8Z',
    sdk: 'M8 9l-3 3 3 3m8-6l3 3-3 3m-3-9-4 12',
    playground:
        'M5 4.5v15a1 1 0 0 0 1.5.86l12-7.5a1 1 0 0 0 0-1.72l-12-7.5A1 1 0 0 0 5 4.5Z',
    runs: 'M4 5h16M4 12h16M4 19h10M19 16l2 2-2 2',
    llm: 'M12 3l2.5 5.5L20 11l-5.5 2.5L12 19l-2.5-5.5L4 11l5.5-2.5L12 3Z',
    governance: 'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Z',
    settings:
        'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm8.4 3a8.4 8.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a8 8 0 0 0-2.2-1.3L15 1h-4l-.6 2.6A8 8 0 0 0 8.2 4.9l-2.4-1-2 3.4 2 1.6a8.4 8.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1a8 8 0 0 0 2.2 1.3L11 23h4l.6-2.6a8 8 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.2-.9.2-1.3Z',
    // ui
    search: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm6 13 4 4',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
    chevdown: 'M6 9l6 6 6-6',
    chevright: 'M9 6l6 6-6 6',
    chevleft: 'M15 6l-6 6 6 6',
    plus: 'M12 5v14M5 12h14',
    check: 'M5 12l5 5L20 6',
    checkCircle: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm-3-10 2 2 4-4',
    x: 'M6 6l12 12M18 6 6 18',
    xCircle: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm3-13-6 6m0-6 6 6',
    alert: 'M12 2 1 21h22L12 2Zm0 7v5m0 4h.01',
    'shield-alert':
        'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Zm0 5v4m0 4h.01',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-15v5l3 2',
    key: 'M14 7a4 4 0 1 1-5.7 5.6L3 18v3h3l1-1h2v-2h2l1.3-1.3A4 4 0 0 1 14 7Zm2 2h.01',
    sparkles:
        'M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3Zm6 9 .9 2.1L21 15l-2.1.9L18 18l-.9-2.1L15 15l2.1-.9L18 12Z',
    copy: 'M9 9h10a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V10a1 1 0 0 1 1-1ZM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1',
    refresh:
        'M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16m0 5v-5h5',
    external:
        'M14 4h6v6m0-6L10 14M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6',
    arrowRight: 'M5 12h14m-6-6 6 6-6 6',
    dots: 'M5 12h.01M12 12h.01M19 12h.01',
    filter: 'M3 5h18l-7 8v6l-4-2v-4L3 5Z',
    sun: 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-5v2m0 18v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M2 12h2m18 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
    moon: 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z',
    play: 'M6 4.5v15a1 1 0 0 0 1.5.86l12-7.5a1 1 0 0 0 0-1.72l-12-7.5A1 1 0 0 0 6 4.5Z',
    pause: 'M8 5h3v14H8zM13 5h3v14h-3z',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0',
    doc: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6M9 17h6',
    code: 'M8 9l-3 3 3 3m8-6 3 3-3 3M13 6l-2 12',
    layers: 'M12 3 2 8l10 5 10-5-10-5Zm10 9-10 5L2 12m20 5-10 5L2 17',
    link: 'M9 15l6-6m-4-3 1.5-1.5a4 4 0 0 1 5.7 5.7L16.5 12m-9 0L6 13.5a4 4 0 0 0 5.7 5.7L13 18',
    database:
        'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3Zm8 3v6c0 1.7-3.6 3-8 3s-8-1.3-8-3V6m16 6v6c0 1.7-3.6 3-8 3s-8-1.3-8-3v-6',
    globe: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm-9-10h18M12 2a14 14 0 0 1 0 20M12 2a14 14 0 0 0 0 20',
    book: 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Zm2 13h13',
    cpu: 'M7 7h10v10H7zM9.5 9.5h5v5h-5zM9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2',
    bolt: 'M13 2 4 14h6l-1 8 9-12h-6l1-8Z',
    flask: 'M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3M8 15h8',
    shield: 'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Z',
    send: 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z',
    edit: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z',
    trash: 'M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13',
    archive: 'M3 5h18v4H3zM5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4',
    power: 'M12 4v8m5.5-5.5a8 8 0 1 1-11 0',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
    grid: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
    list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    pin: 'M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
    building:
        'M3 21h18M5 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M15 21V9h3a1 1 0 0 1 1 1v11M8 8h2m-2 4h2m-2 4h2',
    flow: 'M5 4h4v4H5zM15 8h4v4h-4zM5 16h4v4H5zM9 6h4a2 2 0 0 1 2 2M9 18h4a2 2 0 0 1 2-2',
    check2: 'M20 6 9 17l-5-5',
    info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-14h.01M11 12h1v5h1',
    lock: 'M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Zm2 0V8a4 4 0 0 1 8 0v3',
    download:
        'M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2',
    anchor: 'M12 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm0 0v13M5 12H3m0 0a9 9 0 0 0 18 0m0 0h-2',
    menu: 'M4 6h16M4 12h16M4 18h16',
    server: 'M4 5h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm0 8h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1Zm3-5h.01M7 16h.01',
};

export type IconName = keyof typeof ICON_PATHS | string;

export function Icon({
    name,
    size = 16,
    className = '',
    style = {},
    strokeWidth = 1.7,
    fill = false,
}: {
    name: IconName;
    size?: number;
    className?: string;
    style?: CSSProperties;
    strokeWidth?: number;
    fill?: boolean;
}) {
    const d = ICON_PATHS[name];

    if (!d) {
        return null;
    }

    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            className={className}
            fill={fill ? 'currentColor' : 'none'}
            stroke={fill ? 'none' : 'currentColor'}
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            style={{ flexShrink: 0, ...style }}
            aria-hidden="true"
        >
            <path d={d} />
        </svg>
    );
}
