/* ============================================================
   MAAC — Cross-screen shared helpers
   ============================================================ */
import type { CSSProperties, ReactNode } from 'react';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import type { Scope } from '@/maac/personas';
import { useMaacData } from '@/maac/use-data';
import {
    Avatar,
    Badge,
    Btn,
    Card,
    EmptyState,
    PageHeader,
    RunBadge,
    Table,
    Td,
    Tr,
} from './ui';

/** Small uppercase section label (shared by SDK + Playground panels). */
export function Lbl({
    children,
    style = {},
}: {
    children: ReactNode;
    style?: CSSProperties;
}) {
    return (
        <div
            style={{
                fontSize: 11,
                fontWeight: 700,
                color: 'var(--text-3)',
                textTransform: 'uppercase',
                letterSpacing: 0.4,
                marginBottom: 7,
                ...style,
            }}
        >
            {children}
        </div>
    );
}

/** Run-history table for an application (shared by Application + Agent detail). */
export function AppHistory({ app }: { app: { id: string } }) {
    const { go } = useMaacNav();
    const MAAC = useMaacData();
    const appRuns = MAAC.runs.filter((r) => r.appId === app.id);

    return (
        <Table
            columns={[
                { label: 'Run ID' },
                { label: 'Agent' },
                { label: 'Caller' },
                { label: 'Status' },
                { label: 'Tokens', align: 'right' },
                { label: 'Latency', align: 'right' },
                { label: 'Started', align: 'right' },
            ]}
        >
            {appRuns.map((r) => (
                <Tr key={r.id} onClick={() => go('run', { id: r.id })}>
                    <Td mono strong>
                        {r.id}
                    </Td>
                    <Td>
                        {MAAC.agentById(r.agentId)?.name.replace(' Agent', '')}
                    </Td>
                    <Td mono>{r.caller}</Td>
                    <Td>
                        <RunBadge status={r.status} dot />
                    </Td>
                    <Td align="right" mono>
                        {(r.tokensIn + r.tokensOut).toLocaleString()}
                    </Td>
                    <Td align="right" mono>
                        {r.latency}
                    </Td>
                    <Td align="right" style={{ color: 'var(--text-3)' }}>
                        {r.started}
                    </Td>
                </Tr>
            ))}
        </Table>
    );
}

/** Generic "screen not built / not found" fallback. */
export function PlaceholderScreen({ name }: { name: string }) {
    return (
        <div className="route-anim">
            <PageHeader title={name} sub="This screen is being assembled." />
            <Card>
                <EmptyState
                    icon="flask"
                    title="Coming together"
                    desc={`The ${name} screen will appear here.`}
                />
            </Card>
        </div>
    );
}

/** Shown when a record exists but is outside the active persona's scope. */
export function NoAccess({ kind = 'item' }: { kind?: string }) {
    const { go, scope } = useMaacNav();

    return (
        <div className="route-anim">
            <Card style={{ borderColor: 'var(--amber-500)' }}>
                <EmptyState
                    icon="lock"
                    title="Not in your current view"
                    desc={`This ${kind} isn't part of the ${scope.role.view} (${scope.role.name}). Switch views from the account menu in the top-left, or return to your dashboard.`}
                    action={
                        <Btn
                            variant="primary"
                            icon="dashboard"
                            onClick={() => go('dashboard')}
                        >
                            Back to Dashboard
                        </Btn>
                    }
                />
            </Card>
        </div>
    );
}

/** Persona scope banner shown above scoped (non-admin) screens. */
export function ScopeBanner({ scope }: { scope: Scope }) {
    if (scope.isAll) {
        return null;
    }

    const p = scope.role;

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                padding: '11px 15px',
                borderRadius: 'var(--r-lg)',
                border: `1px solid ${p.tone}`,
                background: `color-mix(in srgb, ${p.tone} 9%, var(--surface))`,
                marginBottom: 16,
            }}
        >
            <span
                style={{
                    width: 32,
                    height: 32,
                    borderRadius: 8,
                    background: p.tone,
                    color: '#fff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                <Icon name="eye" size={17} />
            </span>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12.5, fontWeight: 700 }}>
                    {p.view} — {p.name}{' '}
                    <span style={{ color: p.tone }}>· {p.role}</span>
                </div>
                <div
                    style={{
                        fontSize: 11.5,
                        color: 'var(--text-2)',
                        marginTop: 1,
                    }}
                >
                    {p.blurb} Showing <b>{scope.projects.length}</b> projects ·{' '}
                    <b>{scope.agents.length}</b> agents ·{' '}
                    <b>{scope.tools.length}</b> tools.
                </div>
            </div>
            <Badge tone="neutral" soft icon="lock">
                Scoped view
            </Badge>
        </div>
    );
}

/** Vertical audit timeline (tool detail audit history, etc.). */
export type TimelineItem = {
    icon: string;
    color: string;
    title: string;
    by: string;
    time: string;
};
export function Timeline({ items }: { items: TimelineItem[] }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {items.map((it, i) => (
                <div key={i} style={{ display: 'flex', gap: 12 }}>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                        }}
                    >
                        <span
                            style={{
                                width: 28,
                                height: 28,
                                borderRadius: 999,
                                background: 'var(--surface-2)',
                                border: `1.5px solid ${it.color}`,
                                color: it.color,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name={it.icon} size={14} />
                        </span>
                        {i < items.length - 1 && (
                            <span
                                style={{
                                    flex: 1,
                                    width: 2,
                                    background: 'var(--border)',
                                    minHeight: 14,
                                }}
                            />
                        )}
                    </div>
                    <div
                        style={{ paddingBottom: i < items.length - 1 ? 16 : 0 }}
                    >
                        <div style={{ fontSize: 12.5, fontWeight: 600 }}>
                            {it.title}
                        </div>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--text-3)',
                                marginTop: 2,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 6,
                            }}
                        >
                            <Avatar name={it.by} size={15} />
                            {it.by} · {it.time}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Renders a prototype input/output map as a JSON-schema preview string. */
export function schemaJson(obj: Record<string, string>): string {
    const props = Object.entries(obj)
        .map(([k, v]) => {
            const [type, fmt] = String(v).split('·');

            return `    "${k}": { "type": "${type.replace('?', '').trim()}"${fmt ? `, "format": "${fmt.trim()}"` : ''} }`;
        })
        .join(',\n');
    const required = Object.entries(obj)
        .filter(([, v]) => !String(v).includes('?'))
        .map(([k]) => `"${k}"`);

    return `{\n  "type": "object",\n  "properties": {\n${props}\n  },\n  "required": [${required.join(', ')}]\n}`;
}

/**
 * Generates a client-side tool handler stub in the requested language, using the
 * real `@qatar-navigation-milaha/sdk` `ToolHandlerRegistry` API. The handler is keyed by the tool's
 * contract slug, takes `(args, ctx)`, and returns the tool's output schema shape;
 * authorization is the app's own concern (MAAC's `ctx` is just `{ run, toolCall }`).
 */
export function sdkStub(
    tool: { id: string },
    lang: 'ts' | 'php' | 'py',
    argList: string[],
    outList: string[],
): string {
    const perm = `${tool.id.toLowerCase()}:read`;

    if (lang === 'php') {
        return `<?php
use Maac\\Sdk\\Tools\\ToolContext;
use Maac\\Sdk\\Tools\\ToolHandlerRegistry;

// MAAC defines the contract; your app owns the execution (keyed by the tool slug).
$registry = new ToolHandlerRegistry;

$registry->registerCallable('${tool.id}', function (array $args, ToolContext $ctx): array {
    // Enforce YOUR app's own authorization (e.g. '${perm}'), then read your own data.
    $result = YourApp::query([
${argList.map((a) => `        '${a}' => $args['${a}'] ?? null,`).join('\n')}
    ]);

    // Return a result matching the tool's output schema.
    return [
${outList.map((o) => `        '${o}' => $result['${o}'],`).join('\n')}
    ];
});`;
    }

    if (lang === 'py') {
        const pyFn = tool.id.replace(/[^a-zA-Z0-9_]/g, '_');

        return `from maac_sdk import ToolHandlerRegistry

registry = ToolHandlerRegistry()


# MAAC defines the contract; your app owns the execution (keyed by the tool slug).
@registry.register("${tool.id}")
def ${pyFn}(args: dict, ctx: dict) -> dict:
    # Enforce YOUR app's own authorization (e.g. "${perm}"), then read your own data.
    result = your_app.query(
${argList.map((a) => `        ${a}=args.get("${a}"),`).join('\n')}
    )

    # Return a result matching the tool's output schema.
    return {
${outList.map((o) => `        "${o}": result["${o}"],`).join('\n')}
    }`;
    }

    return `import { ToolHandlerRegistry } from "@qatar-navigation-milaha/sdk";

// MAAC defines the contract; your app owns the execution (keyed by the tool slug).
const registry = new ToolHandlerRegistry();

registry.register("${tool.id}", (args, ctx) => {
  // Enforce YOUR app's own authorization (e.g. "${perm}"), then read your own data.
  const result = yourApp.query({
${argList.map((a) => `    ${a}: args.${a},`).join('\n')}
  });

  // Return a result matching the tool's output schema.
  return {
${outList.map((o) => `    ${o}: result.${o},`).join('\n')}
  };
});`;
}
