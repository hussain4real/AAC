/* ============================================================
   MAACC — Tool Version Journey (reporting)
   A persisted, queryable timeline of each client-side tool: its
   contract versions over time (the tool's journey) and each
   application's reported implementation status transitions
   (the consumer's journey — required → implemented → outdated →
   incompatible → recovered). Reads the two append-only histories
   surfaced by App\Support\Sdk\VersionJourney.
   ============================================================ */
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import VersionJourneyExportRoutes from '@/actions/App/Http/Controllers/Maacc/VersionJourneyExportController';
import {
    Badge,
    Btn,
    Card,
    EmptyState,
    EnvBadge,
    ImplBadge,
    PageHeader,
    SectionHeader,
    Segmented,
    Table,
    Td,
    Tr,
} from '@/components/maacc/ui';
import type { Tone } from '@/components/maacc/ui';
import { Icon } from '@/maacc/icons';
import { useMaaccNav } from '@/maacc/nav';

type JourneyVersion = {
    sequence: number;
    version: string;
    execution_mode: string;
    schema_fingerprint: string;
    notes: string | null;
    changed_by: string | null;
    created_at: string | null;
    is_current: boolean;
    input_schema: Record<string, string>;
    output_schema: Record<string, string>;
};

type JourneyImplementation = {
    application: string;
    application_slug: string;
    environment: string;
    status: string;
    status_label: string;
    implemented_version: string | null;
    schema_fingerprint: string | null;
    handler_name: string | null;
    last_validated_at: string | null;
};

type JourneyEvent = {
    id: string;
    tool: string;
    tool_slug: string;
    application: string;
    application_slug: string;
    environment: string;
    status: string;
    status_label: string;
    previous_status: string | null;
    previous_status_label: string | null;
    reason: string;
    reason_label: string;
    reported_version: string | null;
    contract_version: string;
    actor: string | null;
    created_at: string | null;
};

type JourneyTool = {
    slug: string;
    name: string;
    execution_mode: string;
    current_version: string;
    owner: string;
    application: string | null;
    versions: JourneyVersion[];
    implementations: JourneyImplementation[];
    drift_count: number;
    events: JourneyEvent[];
    events_truncated: boolean;
};

type JourneyAppTool = {
    tool: string;
    tool_slug: string;
    environment: string;
    status: string;
    status_label: string;
    implemented_version: string | null;
    contract_version: string;
    schema_fingerprint: string | null;
    last_validated_at: string | null;
};

type JourneyApp = {
    slug: string;
    name: string;
    environment: string;
    tools: JourneyAppTool[];
    drift_count: number;
    events: JourneyEvent[];
    events_truncated: boolean;
};

type JourneyReport = {
    tools: JourneyTool[];
    applications: JourneyApp[];
    truncated: boolean;
};

const REASON_TONE: Record<string, Tone> = {
    reported: 'blue',
    contract_changed: 'purple',
};

function fmtDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function fingerprint(value: string | null): string {
    return value ? value.slice(0, 10) : '—';
}

export default function Journey() {
    const { journey } = usePage<{ journey: JourneyReport | null }>().props;
    const data: JourneyReport = journey ?? {
        tools: [],
        applications: [],
        truncated: false,
    };

    const { team } = useMaaccNav();
    const [view, setView] = useState<'tools' | 'apps'>('tools');
    const [toolSlug, setToolSlug] = useState<string | null>(
        data.tools[0]?.slug ?? null,
    );
    const [appSlug, setAppSlug] = useState<string | null>(
        data.applications[0]?.slug ?? null,
    );

    const selectedTool =
        data.tools.find((t) => t.slug === toolSlug) ?? data.tools[0] ?? null;
    const selectedApp =
        data.applications.find((a) => a.slug === appSlug) ??
        data.applications[0] ??
        null;

    const totalVersions = data.tools.reduce(
        (sum, t) => sum + t.versions.length,
        0,
    );
    const totalDrift = data.tools.reduce((sum, t) => sum + t.drift_count, 0);

    return (
        <>
            <Head title="Version Journey" />
            <div className="route-anim">
                <PageHeader
                    title="Version Journey"
                    sub="A persisted timeline of every client-side tool: its contract versions over time, and each application's reported implementation status as it drifts and recovers."
                    actions={
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                alignItems: 'center',
                            }}
                        >
                            <Segmented
                                value={view}
                                onChange={(v) => setView(v as 'tools' | 'apps')}
                                options={[
                                    {
                                        value: 'tools',
                                        label: 'By tool',
                                        icon: 'tools',
                                    },
                                    {
                                        value: 'apps',
                                        label: 'By application',
                                        icon: 'apps',
                                    },
                                ]}
                            />
                            <Btn
                                size="sm"
                                variant="soft"
                                icon="download"
                                onClick={() => {
                                    window.location.href =
                                        VersionJourneyExportRoutes.download.url(
                                            team,
                                            { query: { format: 'json' } },
                                        );
                                }}
                            >
                                JSON
                            </Btn>
                            <Btn
                                size="sm"
                                variant="soft"
                                icon="download"
                                onClick={() => {
                                    window.location.href =
                                        VersionJourneyExportRoutes.download.url(
                                            team,
                                            { query: { format: 'csv' } },
                                        );
                                }}
                            >
                                CSV
                            </Btn>
                        </div>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: 12,
                        marginBottom: 16,
                    }}
                >
                    <Stat
                        label="Client-side tools"
                        value={data.tools.length}
                        icon="tools"
                    />
                    <Stat
                        label="Contract versions tracked"
                        value={totalVersions}
                        icon="clock"
                        tone="blue"
                    />
                    <Stat
                        label="Handlers drifted"
                        value={totalDrift}
                        icon="alert"
                        tone={totalDrift ? 'red' : 'teal'}
                    />
                </div>

                {data.truncated && (
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-3)',
                            marginBottom: 12,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                    >
                        <Icon name="info" size={13} />
                        Showing the most recent 100 events per item. Use the
                        export for the full history.
                    </div>
                )}

                {data.tools.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon="clock"
                            title="No client-side tools yet"
                            desc="Once an application reports a client-side tool handler — and as its contract evolves — the version journey and drift timeline appear here."
                        />
                    </Card>
                ) : view === 'tools' ? (
                    <ToolsView
                        tools={data.tools}
                        selected={selectedTool}
                        onSelect={setToolSlug}
                    />
                ) : (
                    <AppsView
                        apps={data.applications}
                        selected={selectedApp}
                        onSelect={setAppSlug}
                    />
                )}
            </div>
        </>
    );
}

function ToolsView({
    tools,
    selected,
    onSelect,
}: {
    tools: JourneyTool[];
    selected: JourneyTool | null;
    onSelect: (slug: string) => void;
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '300px 1fr',
                gap: 16,
                alignItems: 'start',
            }}
        >
            <Card pad={false}>
                {tools.map((tool) => (
                    <SelectRow
                        key={tool.slug}
                        active={selected?.slug === tool.slug}
                        title={tool.name}
                        meta={`v${tool.current_version} · ${tool.versions.length} version${tool.versions.length === 1 ? '' : 's'}`}
                        drift={tool.drift_count}
                        onClick={() => onSelect(tool.slug)}
                    />
                ))}
            </Card>

            {selected && <ToolDetail tool={selected} />}
        </div>
    );
}

function ToolDetail({ tool }: { tool: JourneyTool }) {
    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <Card>
                <SectionHeader
                    title="Contract version history"
                    sub={`The tool's own journey — every functional change mints a new version (${tool.owner}).`}
                    icon="clock"
                />
                {tool.versions.length === 0 ? (
                    <EmptyState
                        icon="clock"
                        title="No versions recorded"
                        desc="Contract changes will appear here as immutable version snapshots."
                    />
                ) : (
                    <div style={{ display: 'grid', gap: 0 }}>
                        {tool.versions.map((version, i) => (
                            <VersionRow
                                key={version.sequence}
                                version={version}
                                last={i === tool.versions.length - 1}
                            />
                        ))}
                    </div>
                )}
            </Card>

            <Card>
                <SectionHeader
                    title="Current implementations"
                    sub="Where each application sits right now, per environment."
                    icon="link"
                />
                {tool.implementations.length === 0 ? (
                    <EmptyState
                        icon="link"
                        title="No reported handlers"
                        desc="No application has reported a handler for this tool yet."
                    />
                ) : (
                    <Table
                        columns={[
                            { label: 'Application' },
                            { label: 'Environment' },
                            { label: 'Status' },
                            { label: 'Reported version' },
                            { label: 'Fingerprint' },
                            { label: 'Validated' },
                        ]}
                    >
                        {tool.implementations.map((impl) => (
                            <Tr
                                key={`${impl.application_slug}-${impl.environment}`}
                            >
                                <Td strong>{impl.application}</Td>
                                <Td>
                                    <EnvBadge env={impl.environment} />
                                </Td>
                                <Td>
                                    <ImplBadge status={impl.status} />
                                </Td>
                                <Td mono>{impl.implemented_version ?? '—'}</Td>
                                <Td mono>
                                    {fingerprint(impl.schema_fingerprint)}
                                </Td>
                                <Td>{fmtDate(impl.last_validated_at)}</Td>
                            </Tr>
                        ))}
                    </Table>
                )}
            </Card>

            <EventsCard
                events={tool.events}
                context="tool"
                truncated={tool.events_truncated}
            />
        </div>
    );
}

function VersionRow({
    version,
    last,
}: {
    version: JourneyVersion;
    last: boolean;
}) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 12,
                padding: '12px 0',
                borderBottom: last ? 'none' : '1px solid var(--border)',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    paddingTop: 2,
                }}
            >
                <span
                    style={{
                        width: 10,
                        height: 10,
                        borderRadius: 10,
                        background: version.is_current
                            ? 'var(--primary)'
                            : 'var(--border-strong, var(--text-3))',
                    }}
                />
                {!last && (
                    <span
                        style={{
                            flex: 1,
                            width: 2,
                            background: 'var(--border)',
                            marginTop: 4,
                        }}
                    />
                )}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        flexWrap: 'wrap',
                    }}
                >
                    <span
                        style={{ fontWeight: 700, fontFamily: 'var(--mono)' }}
                    >
                        v{version.version}
                    </span>
                    {version.is_current && (
                        <Badge tone="teal" soft>
                            Current
                        </Badge>
                    )}
                    <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {fmtDate(version.created_at)}
                    </span>
                    {version.changed_by && (
                        <span style={{ fontSize: 12, color: 'var(--text-3)' }}>
                            · by {version.changed_by}
                        </span>
                    )}
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: 'var(--text-3)',
                        marginTop: 4,
                        fontFamily: 'var(--mono)',
                    }}
                >
                    in {Object.keys(version.input_schema).length} · out{' '}
                    {Object.keys(version.output_schema).length} · fp{' '}
                    {fingerprint(version.schema_fingerprint)}
                </div>
                {version.notes && (
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--text-2)',
                            marginTop: 4,
                        }}
                    >
                        {version.notes}
                    </div>
                )}
            </div>
        </div>
    );
}

function AppsView({
    apps,
    selected,
    onSelect,
}: {
    apps: JourneyApp[];
    selected: JourneyApp | null;
    onSelect: (slug: string) => void;
}) {
    if (apps.length === 0) {
        return (
            <Card>
                <EmptyState
                    icon="apps"
                    title="No applications with client tools"
                    desc="Applications that own client-side tools will appear here."
                />
            </Card>
        );
    }

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: '300px 1fr',
                gap: 16,
                alignItems: 'start',
            }}
        >
            <Card pad={false}>
                {apps.map((app) => (
                    <SelectRow
                        key={app.slug}
                        active={selected?.slug === app.slug}
                        title={app.name}
                        meta={`${app.tools.length} handler${app.tools.length === 1 ? '' : 's'}`}
                        drift={app.drift_count}
                        onClick={() => onSelect(app.slug)}
                    />
                ))}
            </Card>

            {selected && (
                <div style={{ display: 'grid', gap: 16 }}>
                    <Card>
                        <SectionHeader
                            title="Reported handlers"
                            sub="Each client-side tool this application implements, and where it sits against the current contract."
                            icon="link"
                        />
                        {selected.tools.length === 0 ? (
                            <EmptyState
                                icon="link"
                                title="No reported handlers"
                                desc="This application has not reported any client-side tool handlers."
                            />
                        ) : (
                            <Table
                                columns={[
                                    { label: 'Tool' },
                                    { label: 'Environment' },
                                    { label: 'Status' },
                                    { label: 'Reported' },
                                    { label: 'Contract' },
                                ]}
                            >
                                {selected.tools.map((row) => (
                                    <Tr
                                        key={`${row.tool_slug}-${row.environment}`}
                                    >
                                        <Td strong>{row.tool}</Td>
                                        <Td>
                                            <EnvBadge env={row.environment} />
                                        </Td>
                                        <Td>
                                            <ImplBadge status={row.status} />
                                        </Td>
                                        <Td mono>
                                            {row.implemented_version ?? '—'}
                                        </Td>
                                        <Td mono>{row.contract_version}</Td>
                                    </Tr>
                                ))}
                            </Table>
                        )}
                    </Card>

                    <EventsCard
                        events={selected.events}
                        context="app"
                        truncated={selected.events_truncated}
                    />
                </div>
            )}
        </div>
    );
}

function EventsCard({
    events,
    context,
    truncated,
}: {
    events: JourneyEvent[];
    context: 'tool' | 'app';
    truncated: boolean;
}) {
    return (
        <Card>
            <SectionHeader
                title="Implementation timeline"
                sub="The consumer's journey — every report and every drift/recovery transition, newest first."
                icon="runs"
                right={
                    truncated ? (
                        <Badge tone="neutral" soft>
                            Latest 100
                        </Badge>
                    ) : undefined
                }
            />
            {events.length === 0 ? (
                <EmptyState
                    icon="runs"
                    title="No timeline events"
                    desc="Reports and contract-change transitions will appear here as they happen."
                />
            ) : (
                <Table
                    columns={[
                        { label: 'When' },
                        { label: context === 'tool' ? 'Application' : 'Tool' },
                        { label: 'Environment' },
                        { label: 'Transition' },
                        { label: 'Trigger' },
                        { label: 'Versions' },
                    ]}
                >
                    {events.map((event) => (
                        <Tr key={event.id}>
                            <Td>{fmtDate(event.created_at)}</Td>
                            <Td strong>
                                {context === 'tool'
                                    ? event.application
                                    : event.tool}
                            </Td>
                            <Td>
                                <EnvBadge env={event.environment} />
                            </Td>
                            <Td>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 6,
                                    }}
                                >
                                    {event.previous_status ? (
                                        <>
                                            <ImplBadge
                                                status={event.previous_status}
                                            />
                                            <Icon
                                                name="arrowRight"
                                                size={12}
                                                style={{ opacity: 0.5 }}
                                            />
                                        </>
                                    ) : null}
                                    <ImplBadge status={event.status} />
                                </div>
                            </Td>
                            <Td>
                                <Badge
                                    tone={
                                        REASON_TONE[event.reason] ?? 'neutral'
                                    }
                                    soft
                                >
                                    {event.reason_label}
                                </Badge>
                            </Td>
                            <Td mono>
                                {event.reported_version ?? '—'} →{' '}
                                {event.contract_version}
                            </Td>
                        </Tr>
                    ))}
                </Table>
            )}
        </Card>
    );
}

function SelectRow({
    active,
    title,
    meta,
    drift,
    onClick,
}: {
    active: boolean;
    title: string;
    meta: string;
    drift: number;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            style={{
                display: 'flex',
                width: '100%',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 8,
                padding: '11px 14px',
                border: 'none',
                borderBottom: '1px solid var(--border)',
                borderLeft: `3px solid ${active ? 'var(--primary)' : 'transparent'}`,
                background: active ? 'var(--surface-2)' : 'transparent',
                cursor: 'pointer',
                textAlign: 'left',
            }}
        >
            <div style={{ minWidth: 0 }}>
                <div
                    style={{
                        fontWeight: 600,
                        fontSize: 13,
                        color: 'var(--text)',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}
                >
                    {title}
                </div>
                <div style={{ fontSize: 11.5, color: 'var(--text-3)' }}>
                    {meta}
                </div>
            </div>
            {drift > 0 && (
                <Badge tone="red" soft>
                    {drift}
                </Badge>
            )}
        </button>
    );
}

function Stat({
    label,
    value,
    icon,
    tone = 'purple',
}: {
    label: string;
    value: number;
    icon: string;
    tone?: Tone;
}) {
    return (
        <Card>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <Badge tone={tone} soft style={{ height: 30, width: 30 }}>
                    <Icon name={icon} size={15} />
                </Badge>
                <div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{value}</div>
                    <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
                        {label}
                    </div>
                </div>
            </div>
        </Card>
    );
}
