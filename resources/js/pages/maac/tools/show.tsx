/* ============================================================
   MAAC — Tool Detail
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    NoAccess,
    PlaceholderScreen,
    Timeline,
    schemaJson,
} from '@/components/maac/common';
import {
    AgentBadge,
    AppMark,
    Btn,
    Card,
    CodeBlock,
    EnvBadge,
    ExecChip,
    ImplBadge,
    KV,
    PageHeader,
    SectionHeader,
    Segmented,
    SensBadge,
    Table,
    Td,
    Tr,
    Badge,
    scopeBadge,
} from '@/components/maac/ui';
import type { Agent, Tool } from '@/maac/data';
import { Icon } from '@/maac/icons';
import { useMaacNav } from '@/maac/nav';
import { useMaacData } from '@/maac/use-data';

export default function Show({ id }: { id: string }) {
    const { go, scope } = useMaacNav();
    const MAAC = useMaacData();
    const tool = MAAC.toolById(id);

    if (!tool) {
        return <PlaceholderScreen name="Tool" />;
    }

    if (!scope.has.tool(id)) {
        return <NoAccess kind="tool" />;
    }

    const isClient = tool.execMode === 'client';
    const usedByAgents = tool.usedBy
        .map((a) => MAAC.agentById(a))
        .filter((a): a is Agent => Boolean(a));

    return (
        <>
            <Head title={tool ? tool.name : 'Tool'} />
            <div className="route-anim">
                <PageHeader
                    breadcrumb={[
                        { label: 'Tools', onClick: () => go('tools') },
                        { label: tool.name },
                    ]}
                    title={
                        <span className="mono" style={{ fontSize: 21 }}>
                            {tool.name}
                        </span>
                    }
                    badge={
                        <>
                            {scopeBadge(tool.scope)}
                            <ExecChip mode={tool.execMode} />
                        </>
                    }
                    sub={tool.desc}
                    actions={
                        <>
                            <Btn variant="default" icon="edit">
                                Edit
                            </Btn>
                            {isClient && (
                                <Btn variant="default" icon="code">
                                    Generate SDK Stub
                                </Btn>
                            )}
                            <Btn variant="primary" icon="link">
                                Assign to Agent
                            </Btn>
                        </>
                    }
                />

                {isClient && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 12,
                            padding: '14px 16px',
                            background:
                                'linear-gradient(100deg, var(--orange-100), transparent)',
                            borderRadius: 'var(--r-lg)',
                            border: '1px solid var(--orange-400)',
                            marginBottom: 16,
                        }}
                    >
                        <span
                            style={{
                                width: 40,
                                height: 40,
                                borderRadius: 10,
                                background: 'var(--orange-600)',
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <Icon name="link" size={21} />
                        </span>
                        <div>
                            <div
                                style={{
                                    fontSize: 13.5,
                                    fontWeight: 700,
                                    color: 'var(--text)',
                                }}
                            >
                                Client-side tool — implemented by the
                                integrating application
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-2)',
                                    lineHeight: 1.5,
                                    marginTop: 3,
                                }}
                            >
                                This tool must be implemented inside the
                                integrating application using the MAAC SDK.{' '}
                                <b>
                                    MAAC defines the contract; the application
                                    owns the execution.
                                </b>{' '}
                                MAAC never accesses the application's database
                                directly.
                            </div>
                        </div>
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 320px',
                        gap: 14,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <Card pad={false}>
                                <div
                                    style={{
                                        padding: '11px 14px',
                                        borderBottom: '1px solid var(--border)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <Icon
                                        name="download"
                                        size={14}
                                        style={{ color: 'var(--primary)' }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 700,
                                        }}
                                    >
                                        Input schema
                                    </span>
                                </div>
                                <CodeBlock
                                    code={schemaJson(tool.input)}
                                    lang="json"
                                    style={{ border: 'none', borderRadius: 0 }}
                                />
                            </Card>
                            <Card pad={false}>
                                <div
                                    style={{
                                        padding: '11px 14px',
                                        borderBottom: '1px solid var(--border)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <Icon
                                        name="external"
                                        size={14}
                                        style={{ color: 'var(--teal-600)' }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            fontWeight: 700,
                                        }}
                                    >
                                        Output schema
                                    </span>
                                </div>
                                <CodeBlock
                                    code={schemaJson(tool.output)}
                                    lang="json"
                                    style={{ border: 'none', borderRadius: 0 }}
                                />
                            </Card>
                        </div>

                        {isClient && <SDKStubs tool={tool} />}

                        <Card pad={false}>
                            <div style={{ padding: '14px 16px 12px' }}>
                                <SectionHeader
                                    title="Implementation status by application"
                                    icon="apps"
                                    style={{ marginBottom: 0 }}
                                />
                            </div>
                            <Table
                                columns={[
                                    { label: 'Application' },
                                    { label: 'Environment' },
                                    { label: 'Status' },
                                    { label: 'Last validated', align: 'right' },
                                ]}
                            >
                                {(isClient
                                    ? [MAAC.appById(tool.appId ?? '')].filter(
                                          Boolean,
                                      )
                                    : MAAC.apps.slice(0, 2)
                                ).map((app) => (
                                    <Tr
                                        key={app!.id}
                                        onClick={() =>
                                            go('application', { id: app!.id })
                                        }
                                    >
                                        <Td strong>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 9,
                                                }}
                                            >
                                                <AppMark
                                                    code={app!.id}
                                                    size={26}
                                                />
                                                {app!.name}
                                            </div>
                                        </Td>
                                        <Td>
                                            <EnvBadge env={app!.env} />
                                        </Td>
                                        <Td>
                                            {isClient ? (
                                                <ImplBadge status={tool.impl} />
                                            ) : (
                                                <Badge tone="teal" dot>
                                                    Hosted by MAAC
                                                </Badge>
                                            )}
                                        </Td>
                                        <Td
                                            align="right"
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            {tool.impl === 'implemented'
                                                ? '2 hours ago'
                                                : tool.impl === 'outdated'
                                                  ? '8 days ago'
                                                  : 'Never'}
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                        </Card>

                        <Card>
                            <SectionHeader title="Audit history" icon="runs" />
                            <Timeline
                                items={[
                                    {
                                        icon: 'plus',
                                        color: 'var(--primary)',
                                        title: 'Tool contract created',
                                        by:
                                            tool.owner === 'Platform'
                                                ? 'platform.admin'
                                                : 'developer',
                                        time: '3 weeks ago',
                                    },
                                    {
                                        icon: 'edit',
                                        color: 'var(--blue-500)',
                                        title: `Input schema updated to ${tool.scope === 'Agent' ? 'v2' : 'v1'}`,
                                        by: 'r.saleh',
                                        time: '8 days ago',
                                    },
                                    ...(tool.impl === 'implemented'
                                        ? [
                                              {
                                                  icon: 'check2',
                                                  color: 'var(--teal-600)',
                                                  title: 'Implementation validated successfully',
                                                  by: 'sdk.sync',
                                                  time: '2 hours ago',
                                              },
                                          ]
                                        : []),
                                    ...(tool.impl === 'outdated'
                                        ? [
                                              {
                                                  icon: 'alert',
                                                  color: 'var(--amber-500)',
                                                  title: 'Implementation flagged outdated — schema drift',
                                                  by: 'sdk.sync',
                                                  time: '8 days ago',
                                              },
                                          ]
                                        : []),
                                    ...(tool.approval
                                        ? [
                                              {
                                                  icon: 'shield',
                                                  color: 'var(--purple-600)',
                                                  title: 'Approved for production use',
                                                  by: 'k.almansoori',
                                                  time: '5 days ago',
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        </Card>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Card>
                            <SectionHeader title="Contract" icon="info" />
                            <KV
                                cols={1}
                                items={[
                                    {
                                        k: 'Execution mode',
                                        v: <ExecChip mode={tool.execMode} />,
                                    },
                                    { k: 'Scope', v: scopeBadge(tool.scope) },
                                    {
                                        k: 'Data sensitivity',
                                        v: (
                                            <SensBadge
                                                level={tool.sensitivity}
                                            />
                                        ),
                                    },
                                    {
                                        k: 'Requires approval',
                                        v: tool.approval ? 'Yes' : 'No',
                                    },
                                    {
                                        k: 'Timeout',
                                        v: tool.timeout,
                                        mono: true,
                                    },
                                    {
                                        k: 'Max payload',
                                        v: tool.maxPayload,
                                        mono: true,
                                    },
                                    {
                                        k: 'Owner',
                                        v:
                                            tool.owner === 'Platform'
                                                ? 'Platform team'
                                                : (MAAC.appById(tool.owner)
                                                      ?.name ?? tool.owner),
                                    },
                                ]}
                            />
                        </Card>
                        <Card>
                            <SectionHeader
                                title={`Used by ${usedByAgents.length} agents`}
                                icon="agents"
                            />
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 7,
                                }}
                            >
                                {usedByAgents.map((a) => (
                                    <div
                                        key={a.id}
                                        className="maac-row"
                                        onClick={() =>
                                            go('agent', { id: a.id })
                                        }
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 9,
                                            padding: '7px 9px',
                                            borderRadius: 7,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 26,
                                                height: 26,
                                                borderRadius: 7,
                                                background:
                                                    'var(--primary-soft)',
                                                color: 'var(--primary)',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                flexShrink: 0,
                                            }}
                                        >
                                            <Icon name="agents" size={14} />
                                        </span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    fontWeight: 600,
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                }}
                                            >
                                                {a.name}
                                            </div>
                                        </div>
                                        <AgentBadge status={a.status} />
                                    </div>
                                ))}
                            </div>
                        </Card>
                        <Btn variant="danger" icon="archive" full>
                            Deprecate Tool
                        </Btn>
                    </div>
                </div>
            </div>
        </>
    );
}

/* ---- local sub-component (ported verbatim from prototype) ---- */

function SDKStubs({ tool }: { tool: Tool }) {
    const [lang, setLang] = useState<'ts' | 'php' | 'py'>('ts');
    const fn = tool.name;
    const argList = Object.keys(tool.input);
    const stubs: Record<'ts' | 'php' | 'py', string> = {
        ts: `import { maac } from "./maac-client";

// MAAC defines the contract; your app owns the execution.
maac.registerTool("${fn}", async (args, ctx) => {
  // 1. Enforce the caller's permissions
  if (!ctx.user.hasPermission("${tool.id.replace(/^get/, '').toLowerCase()}:read")) {
    return { status: "rejected", reason: "Not permitted" };
  }

  // 2. Query YOUR application's own data
  const data = await db.query({
${argList.map((a) => `    ${a}: args.${a},`).join('\n')}
  });

  // 3. Return a result matching the output schema
  return { summary: data.summary, records: data.records };
});`,
        php: `<?php
use Milaha\\MAAC\\Client;

// MAAC defines the contract; your app owns the execution.
$maac->registerTool('${fn}', function (array $args, Context $ctx) {
    // 1. Enforce the caller's permissions
    if (! $ctx->user->can('${tool.id.replace(/^get/, '').toLowerCase()}:read')) {
        return ['status' => 'rejected', 'reason' => 'Not permitted'];
    }

    // 2. Query YOUR application's own data
    $data = AppData::query([
${argList.map((a) => `        '${a}' => $args['${a}'] ?? null,`).join('\n')}
    ]);

    // 3. Return a result matching the output schema
    return ['summary' => $data->summary, 'records' => $data->records];
});`,
        py: `from maac import maac, Context

# MAAC defines the contract; your app owns the execution.
@maac.tool("${fn}")
async def ${tool.id}(args: dict, ctx: Context):
    # 1. Enforce the caller's permissions
    if not ctx.user.has_permission("${tool.id.replace(/^get/, '').toLowerCase()}:read"):
        return {"status": "rejected", "reason": "Not permitted"}

    # 2. Query YOUR application's own data
    data = await db.query(
${argList.map((a) => `        ${a}=args.get("${a}"),`).join('\n')}
    )

    # 3. Return a result matching the output schema
    return {"summary": data.summary, "records": data.records}`,
    };

    return (
        <Card pad={false}>
            <div
                style={{
                    padding: '13px 16px 12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <SectionHeader
                    title="Generated SDK stub"
                    sub="Copy into the owning application to implement this tool"
                    icon="code"
                    style={{ marginBottom: 0 }}
                />
                <Segmented
                    options={[
                        { value: 'ts', label: 'TypeScript' },
                        { value: 'php', label: 'PHP / Laravel' },
                        { value: 'py', label: 'Python' },
                    ]}
                    value={lang}
                    onChange={(v) => setLang(v as 'ts' | 'php' | 'py')}
                    size="sm"
                />
            </div>
            <CodeBlock
                code={stubs[lang]}
                lang={
                    lang === 'ts'
                        ? 'typescript'
                        : lang === 'php'
                          ? 'php'
                          : 'python'
                }
                style={{ border: 'none', borderRadius: 0 }}
                maxHeight={340}
            />
        </Card>
    );
}
