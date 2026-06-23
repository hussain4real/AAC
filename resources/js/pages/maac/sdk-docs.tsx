/* ============================================================
   MAAC — SDK Documentation
   A proper, multi-language docs page for the MAAC SDK & runtime
   API: install, quick start, the run lifecycle, handler
   implementation, versioning/compatibility, validation, errors,
   the compatibility matrix, and troubleshooting — with copy-paste
   examples for every supported language. Rendered version of
   docs/MAAC_SDK_Integration_Guide.md.
   ============================================================ */
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Badge,
    Btn,
    Card,
    CodeBlock,
    PageHeader,
    SectionHeader,
    Segmented,
    Table,
    Td,
    Tr,
} from '@/components/maac/ui';
import type { Tone } from '@/components/maac/ui';
import { useMaacNav } from '@/maac/nav';

/* ── code samples ── */

const INSTALL = {
    ts: `npm install @maac/sdk        # Node >= 18 (global fetch); zero dependencies`,
    php: `composer require milaha/maac-sdk   # PHP >= 8.2, ext-curl, ext-json`,
    py: `# Python: MAAC generates ready-to-paste handler stubs today
# (see "Implementing a handler"); a packaged client is planned.
# Track status in the compatibility matrix below.`,
};

const QUICKSTART_TS = `import { isCompleted, MaacClient, ToolHandlerRegistry } from '@maac/sdk';

const client = new MaacClient({
  baseUrl: process.env.MAAC_BASE_URL!,
  clientId: process.env.MAAC_CLIENT_ID!,
  clientSecret: process.env.MAAC_CLIENT_SECRET!,
});

// Your data access + permissions live in the handler; MAAC only sees the result.
const registry = new ToolHandlerRegistry().register(
  'fetch-records',
  (args) => ({ records: myRepo.search(String(args.query ?? '')), total: 0 }),
  'fetchRecordsHandler',
);

// One call: report what you implement, then run the agent end-to-end.
await client.reportHandlers(await client.manifest(), registry);
const run = await client.run('ops-agent', 'Summarize today', registry);

console.log(isCompleted(run) ? run.response : \`Run \${run.status}: \${run.error}\`);`;

const QUICKSTART_PHP = `use Maac\\Sdk\\{MaacClient, MaacConfig};
use Maac\\Sdk\\Tools\\{CallableToolHandler, ToolHandlerRegistry};

$client = new MaacClient(MaacConfig::fromEnvironment());

// Your data access + permissions live in the handler; MAAC only sees the result.
$registry = (new ToolHandlerRegistry)->register(new CallableToolHandler(
    'fetch-records',
    fn (array $args): array => ['records' => MyRepo::search((string) ($args['query'] ?? '')), 'total' => 0],
));

// One call: report what you implement, then run the agent end-to-end.
$client->reportHandlers($client->manifest(), $registry);
$run = $client->run('ops-agent', 'Summarize today', $registry);

echo $run->isCompleted() ? $run->response : "Run {$run->status}: {$run->error}";`;

const TOKEN_EXCHANGE = `POST {MAAC_BASE_URL}/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={MAAC_CLIENT_ID}&client_secret={MAAC_CLIENT_SECRET}

# -> { "token_type": "Bearer", "expires_in": 3600, "access_token": "..." }
# The SDK caches the token, refreshes before expiry, and retries once on a 401.`;

const HANDLER_TS = `import { ToolHandlerRegistry } from '@maac/sdk';

const registry = new ToolHandlerRegistry().register(
  'fetch-records',                 // the tool contract slug
  (args, ctx) => {
    // Enforce YOUR caller permissions before returning any data.
    const records = myRepository.search(String(args.query ?? ''));

    // The returned object must satisfy the tool's output schema.
    return { records, total: records.length };
  },
  'fetchRecordsHandler',           // reported handler name
);`;

const HANDLER_PHP = `use Maac\\Sdk\\Tools\\{ToolHandler, ToolContext, ToolHandlerRegistry};

final class FetchRecordsHandler implements ToolHandler
{
    public function tool(): string
    {
        return 'fetch-records'; // the tool contract slug
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        // Enforce YOUR caller permissions before returning any data.
        $records = MyRepository::search((string) ($arguments['query'] ?? ''));

        // The returned array must satisfy the tool's output schema.
        return ['records' => $records, 'total' => count($records)];
    }
}

$registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler);`;

const HANDLER_PY = `from maac_sdk import maac, Context


@maac.tool("fetch-records")  # contract v0.0.1
async def fetch_records(args: dict, ctx: Context) -> dict:
    # Enforce YOUR caller permissions before returning any data.
    if not ctx.user.has_permission("fetch-records:read"):
        return {"status": "rejected", "reason": "Not permitted"}

    records = your_app.query(query=args.get("query"))

    # Result must satisfy the output schema: { records: list, total: int }
    return {"records": records, "total": len(records)}`;

const COMPAT_TS = `import { isSdkCompatible } from '@maac/sdk';

const compatibility = await client.compatibility();

if (!isSdkCompatible(compatibility)) {
  // status is one of: compatible | upgrade_required | ahead | unknown
  throw new Error(\`Upgrade the SDK to >= \${compatibility.minimumClientVersion}.\`);
}`;

const COMPAT_PHP = `$compatibility = $client->compatibility();

if (! $compatibility->isCompatible()) {
    // status is one of: compatible | upgrade_required | ahead | unknown
    throw new RuntimeException(
        "Upgrade the SDK to >= {$compatibility->minimumClientVersion}."
    );
}`;

const RUNTIME_TS = `// Long-running run, driven by polling (services client tools automatically):
const run = await client.runAsync('ops-agent', 'Summarize today', registry, 'caller', {
  intervalMs: 2000,
});

// …or start async and poll the run yourself:
const queued = await client.startRun('ops-agent', 'Long job', undefined, 'async');
const settled = await client.pollRun(queued.runId);

// Receive signed webhooks — register once, then verify every delivery:
import { verifyWebhook } from '@maac/sdk';
const endpoint = await client.registerWebhook('https://app.example.com/hooks', ['*']);
// …inside your webhook route, reject anything that does not verify:
const ok = verifyWebhook(
  rawBody,
  req.headers['x-maac-signature'],
  req.headers['x-maac-webhook-timestamp'],
  endpoint.secret, // stored from registration
);

// Stream a run's lifecycle as Server-Sent Events:
const events = await client.streamRun(run.runId, (e) => console.log(e.event, e.data));`;

const RUNTIME_PHP = `// Long-running run, driven by polling (services client tools automatically):
$run = $client->runAsync('ops-agent', 'Summarize today', $registry, 'caller', ['intervalMs' => 2000]);

// …or start async and poll the run yourself:
$queued = $client->startRun('ops-agent', 'Long job', mode: MaacClient::MODE_ASYNC);
$settled = $client->pollRun($queued->runId);

// Receive signed webhooks — register once, then verify every delivery:
use Maac\\Sdk\\Webhooks\\WebhookSignature;
$endpoint = $client->registerWebhook('https://app.example.com/hooks', ['*']);
// …inside your webhook controller, reject anything that does not verify:
$ok = WebhookSignature::verify(
    $request->getContent(),
    $request->header('X-Maac-Signature'),
    $request->header('X-Maac-Webhook-Timestamp'),
    $endpoint->secret, // stored from registration
);

// Stream a run's lifecycle as Server-Sent Events:
$events = $client->streamRun($run->runId, fn ($e) => printf("%s\\n", $e->event));`;

const SERVER_TOOLS_TS = `// You implement nothing for server-side tools — just invoke the agent.
// The manifest still shows what MAAC runs for you, tagged with its mode:
const manifest = await client.manifest();
for (const agent of manifest.agents) {
  for (const tool of agent.serverTools) {
    // tool.executionMode is 'hosted' | 'http' | 'connector'
    console.log(\`\${agent.slug} runs \${tool.name} server-side (\${tool.executionMode})\`);
  }
}

// agent.tools still lists only the client-side tools you must implement.`;

const SERVER_TOOLS_PHP = `// You implement nothing for server-side tools — just invoke the agent.
// The manifest still shows what MAAC runs for you, tagged with its mode:
$manifest = $client->manifest();
foreach ($manifest->agents as $agent) {
    foreach ($agent->serverTools as $tool) {
        // $tool['execution_mode'] is 'hosted' | 'http' | 'connector'
        echo "{$agent->slug} runs {$tool['name']} server-side ({$tool['execution_mode']})\\n";
    }
}

// $agent->tools still lists only the client-side tools you must implement.`;

const VALIDATE_TS = `import { findTool, ToolTester } from '@maac/sdk';

const tool = findTool(await client.manifest(), 'fetch-records');
const result = await new ToolTester().test(tool!, handler, { query: 'today' });

if (!result.valid) {
  // result.errors lists exactly which input/output rules were violated.
  console.error(result.errors.join('; '));
}`;

const VALIDATE_PHP = `use Maac\\Sdk\\Testing\\ToolTester;

$tool = $client->manifest()->tool('fetch-records');
$result = (new ToolTester)->test($tool, $handler, ['query' => 'today']);

if ($result->fails()) {
    // $result->errors lists exactly which input/output rules were violated.
    echo implode('; ', $result->errors);
}`;

/* ── env vars ── */

const ENV_VARS: {
    name: string;
    required: string;
    example: string;
    notes: string;
}[] = [
    {
        name: 'MAAC_BASE_URL',
        required: 'yes',
        example: 'https://maac.test',
        notes: 'Base URL of the MAAC instance (no trailing path).',
    },
    {
        name: 'MAAC_CLIENT_ID',
        required: 'yes',
        example: '9c3f…',
        notes: "The credential's client id.",
    },
    {
        name: 'MAAC_CLIENT_SECRET',
        required: 'yes',
        example: 's3cr3t…',
        notes: 'Shown once on generate/rotate.',
    },
    {
        name: 'MAAC_AGENT_SLUG',
        required: 'reference apps',
        example: 'ops-agent',
        notes: 'The published agent to invoke.',
    },
    {
        name: 'MAAC_TIMEOUT',
        required: 'no',
        example: '30',
        notes: 'Per-request timeout in seconds (PHP SDK).',
    },
];

/* ── error matrix ── */

const ERRORS: { code: string; http: number; meaning: string }[] = [
    {
        code: 'invalid_token',
        http: 401,
        meaning:
            'Token missing/expired. The SDK refreshes and retries automatically.',
    },
    {
        code: 'unknown_client',
        http: 401,
        meaning:
            'Token not tied to a registered application. Check the credential.',
    },
    {
        code: 'credential_revoked',
        http: 403,
        meaning: 'The credential was revoked. Generate a new one.',
    },
    {
        code: 'agent_not_found',
        http: 404,
        meaning:
            'No such agent for your application (tenant isolation). Check the slug + ownership.',
    },
    {
        code: 'agent_not_published',
        http: 409,
        meaning: 'The agent is a draft. Publish it in the console.',
    },
    {
        code: 'run_not_waiting',
        http: 409,
        meaning: 'Submitting a tool result to a run that is not paused.',
    },
    {
        code: 'payload_too_large',
        http: 413,
        meaning:
            "Tool result exceeds the contract's max_payload_kb. The run stays resumable.",
    },
    {
        code: 'invalid_tool_result',
        http: 422,
        meaning:
            'Your result failed the output schema. error.errors lists the problems.',
    },
    {
        code: 'quota_exceeded',
        http: 429,
        meaning:
            'A per-period run/token quota was reached. Back off / raise the quota.',
    },
];

/* ── compatibility matrix ── */

const MATRIX: {
    name: string;
    version: string;
    status: string;
    tone: Tone;
    notes: string;
}[] = [
    {
        name: 'PHP SDK (milaha/maac-sdk)',
        version: '0.2.0',
        status: 'Supported',
        tone: 'teal',
        notes: 'PHP ≥ 8.2, ext-curl. Default cURL transport.',
    },
    {
        name: 'TypeScript SDK (@maac/sdk)',
        version: '0.2.0',
        status: 'Supported',
        tone: 'teal',
        notes: 'Node ≥ 18 (global fetch); zero dependencies.',
    },
    {
        name: 'Laravel reference consumer',
        version: '—',
        status: 'Supported',
        tone: 'teal',
        notes: 'Service provider + Artisan command.',
    },
    {
        name: 'Plain-PHP CLI consumer',
        version: '—',
        status: 'Supported',
        tone: 'teal',
        notes: 'No framework. Proves the PHP SDK is framework-agnostic.',
    },
    {
        name: 'Node / TypeScript consumer',
        version: '—',
        status: 'Supported',
        tone: 'teal',
        notes: 'Proves the contract is not Laravel/PHP-only.',
    },
    {
        name: 'Python SDK',
        version: '—',
        status: 'Experimental',
        tone: 'amber',
        notes: 'Stubs are generated by MAAC; a packaged client is coming soon.',
    },
    {
        name: 'Async, polling, streaming & webhooks',
        version: '0.1.0',
        status: 'Supported',
        tone: 'teal',
        notes: 'Queue long-running runs; poll, stream (SSE), or receive signed webhooks.',
    },
    {
        name: 'Remote HTTP & MCP connector tools',
        version: '0.2.0',
        status: 'Supported',
        tone: 'teal',
        notes: 'MAAC executes these server-side; the manifest tags them so the app implements nothing.',
    },
];

const LIFECYCLE: { title: string; body: string }[] = [
    {
        title: 'Authenticate',
        body: 'Exchange the credential for a short-lived bearer token (the SDK does this for you).',
    },
    {
        title: 'Fetch the manifest',
        body: 'GET /api/v1/manifest lists the agents you may invoke and the client-side tools you must implement, with schemas, versions, and schema_fingerprint.',
    },
    {
        title: 'Implement handlers',
        body: "Write a local handler per client-side tool that returns data satisfying the tool's output schema.",
    },
    {
        title: 'Report implementations',
        body: 'POST /api/v1/tool-implementations with the tool, handler name, version, and fingerprint. MAAC reconciles each to implemented / outdated / incompatible.',
    },
    {
        title: 'Invoke the agent',
        body: 'POST /api/v1/agents/{agent_slug}/runs with input. The run completes, fails, or pauses with a tool_call.',
    },
    {
        title: 'Service the pause',
        body: 'When waiting_for_client, run the matching handler with the supplied arguments and submit the result to POST /api/v1/runs/{run_id}/tool-results.',
    },
    {
        title: 'Repeat',
        body: "Until the run is terminal, then read the final response. The SDK's run() does steps 5–7 for you, given a handler registry.",
    },
];

const TROUBLESHOOT: { q: string; a: string }[] = [
    {
        q: 'tool_not_found when reporting',
        a: "The tool slug doesn't exist for your application, or it isn't a client-side tool. Re-check the manifest's tools[].name.",
    },
    {
        q: 'Tool stuck on incompatible',
        a: 'Your reported schema_fingerprint no longer matches the contract — its schema changed. Update your handler to the new shape and re-report the fingerprint from the current manifest.',
    },
    {
        q: 'Tool stuck on outdated',
        a: 'You reported an older contract version. Re-fetch the manifest and report the current version.',
    },
    {
        q: 'invalid_tool_result',
        a: "Your handler's return value doesn't match the output schema. Validate it locally with the SDK ToolTester before reporting; inspect output_schema on the tool_call.",
    },
    {
        q: 'Nothing appears in the MAAC console',
        a: "Confirm you're using the credential for the same environment you're inspecting, and that the agent is published.",
    },
];

/* ── sub-components ── */

interface Sample {
    value: string;
    label: string;
    lang: string;
    code: string;
}

/** A language-switchable code block. */
function CodeTabs({ samples }: { samples: Sample[] }) {
    const [active, setActive] = useState(samples[0].value);
    const current = samples.find((s) => s.value === active) ?? samples[0];

    return (
        <div>
            <div style={{ marginBottom: 8 }}>
                <Segmented
                    options={samples.map((s) => ({
                        value: s.value,
                        label: s.label,
                    }))}
                    value={active}
                    onChange={setActive}
                    size="sm"
                />
            </div>
            <CodeBlock code={current.code} lang={current.lang} />
        </div>
    );
}

interface DocSectionProps {
    id: string;
    title: string;
    sub?: string;
    icon: string;
    children: ReactNode;
}

function DocSection({ id, title, sub, icon, children }: DocSectionProps) {
    return (
        <section id={id} style={{ scrollMarginTop: 84, marginBottom: 26 }}>
            <SectionHeader title={title} sub={sub} icon={icon} />
            <Card>{children}</Card>
        </section>
    );
}

const SECTIONS: { id: string; label: string }[] = [
    { id: 'overview', label: 'Overview' },
    { id: 'install', label: 'Install' },
    { id: 'prerequisites', label: 'Prerequisites' },
    { id: 'env', label: 'Environment variables' },
    { id: 'quickstart', label: 'Quick start' },
    { id: 'lifecycle', label: 'The run lifecycle' },
    { id: 'runtime-modes', label: 'Runtime modes' },
    { id: 'server-tools', label: 'Server-side tools' },
    { id: 'handlers', label: 'Implementing a handler' },
    { id: 'versioning', label: 'Versioning & compatibility' },
    { id: 'validate', label: 'Validate before reporting' },
    { id: 'errors', label: 'Error handling' },
    { id: 'matrix', label: 'Compatibility matrix' },
    { id: 'troubleshooting', label: 'Troubleshooting' },
    { id: 'reference', label: 'Reference apps' },
];

/* ── page ── */

export default function SdkDocs() {
    const { go } = useMaacNav();
    const [active, setActive] = useState(SECTIONS[0].id);

    // Scrollspy: the active section is the last one whose top has crossed the
    // reading line near the top of the viewport. The bottom spacer guarantees
    // every section — including the last — can reach that line, so the TOC
    // tracks the scroll position on both manual scroll and click-to-jump.
    useEffect(() => {
        const scroller: HTMLElement | Window =
            document.querySelector<HTMLElement>('main.maac-scroll') ?? window;

        const compute = () => {
            const line = 150;
            let current = SECTIONS[0].id;

            for (const s of SECTIONS) {
                const el = document.getElementById(s.id);

                if (el && el.getBoundingClientRect().top <= line) {
                    current = s.id;
                }
            }

            setActive(current);
        };

        compute();
        scroller.addEventListener('scroll', compute, { passive: true });
        window.addEventListener('resize', compute);

        return () => {
            scroller.removeEventListener('scroll', compute);
            window.removeEventListener('resize', compute);
        };
    }, []);

    const jump = (id: string) => {
        document
            .getElementById(id)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <>
            <Head title="SDK Documentation" />
            <div className="route-anim">
                <PageHeader
                    title="SDK Documentation"
                    sub="Connect any application to MAAC using only the public SDK & runtime APIs — token exchange, manifest sync, implementation reporting, and pause/resume agent runs."
                    actions={
                        <Btn
                            variant="default"
                            icon="chevleft"
                            onClick={() => go('sdk')}
                        >
                            Back to SDK Center
                        </Btn>
                    }
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '212px 1fr',
                        gap: 20,
                        alignItems: 'start',
                    }}
                >
                    {/* table of contents */}
                    <Card
                        pad={false}
                        style={{
                            position: 'sticky',
                            top: 16,
                            overflow: 'hidden',
                        }}
                    >
                        <div
                            style={{
                                padding: '12px 14px',
                                borderBottom: '1px solid var(--border)',
                                fontSize: 11,
                                fontWeight: 700,
                                letterSpacing: 0.4,
                                textTransform: 'uppercase',
                                color: 'var(--text-3)',
                            }}
                        >
                            On this page
                        </div>
                        <div style={{ padding: 6 }}>
                            {SECTIONS.map((s) => {
                                const isActive = active === s.id;

                                return (
                                    <button
                                        key={s.id}
                                        onClick={() => jump(s.id)}
                                        className="maac-link"
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                        style={{
                                            display: 'block',
                                            width: '100%',
                                            textAlign: 'left',
                                            border: 'none',
                                            cursor: 'pointer',
                                            padding: '7px 10px',
                                            borderRadius: 7,
                                            fontSize: 12.5,
                                            fontWeight: isActive ? 600 : 400,
                                            background: isActive
                                                ? 'var(--primary-soft)'
                                                : 'none',
                                            color: isActive
                                                ? 'var(--primary)'
                                                : 'var(--text-2)',
                                        }}
                                    >
                                        {s.label}
                                    </button>
                                );
                            })}
                        </div>
                    </Card>

                    {/* content */}
                    <div>
                        <DocSection
                            id="overview"
                            title="Overview"
                            icon="info"
                            sub="The MAAC integration model in one paragraph"
                        >
                            <p
                                style={{
                                    fontSize: 13.5,
                                    lineHeight: 1.65,
                                    color: 'var(--text-2)',
                                    margin: 0,
                                }}
                            >
                                MAAC owns the agent, its prompt, its approved
                                model, and the <b>tool contracts</b> (name,
                                input/output schema, version). Your application
                                owns the <b>handlers</b> that implement
                                client-side tools against your own data and
                                permissions. At runtime MAAC pauses an agent run
                                when the model needs a client-side tool, returns
                                the tool name and arguments to your app, your
                                handler runs locally, you submit the result, and
                                MAAC resumes the run.{' '}
                                <b>MAAC never reaches into your database.</b>
                            </p>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    marginTop: 14,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <Badge tone="purple" icon="link">
                                    API contract v0.0.1
                                </Badge>
                                <Badge tone="teal">milaha/maac-sdk (PHP)</Badge>
                                <Badge tone="teal">
                                    @maac/sdk (TypeScript)
                                </Badge>
                            </div>
                        </DocSection>

                        <DocSection
                            id="install"
                            title="Install"
                            icon="download"
                            sub="Add the SDK for your stack"
                        >
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'bash',
                                        code: INSTALL.ts,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'bash',
                                        code: INSTALL.php,
                                    },
                                    {
                                        value: 'py',
                                        label: 'Python',
                                        lang: 'bash',
                                        code: INSTALL.py,
                                    },
                                ]}
                            />
                        </DocSection>

                        <DocSection
                            id="prerequisites"
                            title="Prerequisites"
                            icon="check2"
                            sub="A MAAC operator sets this up once, in the console"
                        >
                            <ol
                                style={{
                                    margin: 0,
                                    paddingLeft: 18,
                                    fontSize: 13,
                                    lineHeight: 1.7,
                                    color: 'var(--text-2)',
                                }}
                            >
                                <li>
                                    <b>Register your application</b>{' '}
                                    (Applications → Register) and note its
                                    environment.
                                </li>
                                <li>
                                    <b>Generate a credential</b> (Applications →
                                    app → Credentials → Generate). The client
                                    secret is shown <b>only once</b> — copy it
                                    immediately. You can rotate it later.
                                </li>
                                <li>
                                    Make sure a <b>published agent</b> exists in
                                    a project under your application, wired to
                                    an approved model and your client-side tool
                                    contract(s).
                                </li>
                            </ol>
                        </DocSection>

                        <DocSection
                            id="env"
                            title="Environment variables"
                            icon="key"
                            sub="The SDKs and reference apps read these"
                        >
                            <Table
                                columns={[
                                    { label: 'Variable' },
                                    { label: 'Required' },
                                    { label: 'Example' },
                                    { label: 'Notes' },
                                ]}
                            >
                                {ENV_VARS.map((v) => (
                                    <Tr key={v.name}>
                                        <Td mono strong>
                                            {v.name}
                                        </Td>
                                        <Td>{v.required}</Td>
                                        <Td mono>{v.example}</Td>
                                        <Td style={{ color: 'var(--text-2)' }}>
                                            {v.notes}
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                            <div style={{ marginTop: 14 }}>
                                <SectionHeader
                                    title="Token exchange"
                                    sub="OAuth2 client_credentials — handled for you, shown for reference"
                                    icon="lock"
                                    style={{ marginBottom: 8 }}
                                />
                                <CodeBlock code={TOKEN_EXCHANGE} lang="http" />
                            </div>
                        </DocSection>

                        <DocSection
                            id="quickstart"
                            title="Quick start"
                            icon="bolt"
                            sub="Report your handlers, then run an agent end-to-end in one call"
                        >
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: QUICKSTART_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: QUICKSTART_PHP,
                                    },
                                ]}
                            />
                            <p
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                    marginTop: 12,
                                    marginBottom: 0,
                                }}
                            >
                                The reference consumers in{' '}
                                <span className="mono">reference-apps/</span>{' '}
                                (Laravel, plain-PHP CLI, and Node) are runnable
                                end-to-end versions of this.
                            </p>
                        </DocSection>

                        <DocSection
                            id="lifecycle"
                            title="The run lifecycle"
                            icon="refresh"
                            sub="What the SDK's run() does under the hood"
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {LIFECYCLE.map((step, i) => (
                                    <div
                                        key={step.title}
                                        style={{
                                            display: 'flex',
                                            gap: 12,
                                            alignItems: 'flex-start',
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 22,
                                                height: 22,
                                                borderRadius: 999,
                                                flexShrink: 0,
                                                background:
                                                    'var(--primary-soft)',
                                                color: 'var(--primary)',
                                                fontSize: 12,
                                                fontWeight: 700,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                            }}
                                        >
                                            {i + 1}
                                        </span>
                                        <div>
                                            <div
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 700,
                                                }}
                                            >
                                                {step.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    color: 'var(--text-2)',
                                                    lineHeight: 1.55,
                                                    marginTop: 1,
                                                }}
                                            >
                                                {step.body}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </DocSection>

                        <DocSection
                            id="runtime-modes"
                            title="Runtime modes"
                            icon="bolt"
                            sub="Long-running runs via polling, streaming, and signed webhooks"
                        >
                            <p
                                style={{
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: 'var(--text-2)',
                                    marginTop: 0,
                                }}
                            >
                                A run can be invoked synchronously (the request
                                blocks until it finishes or pauses) or with{' '}
                                <span className="mono">mode: 'async'</span> —
                                the run is queued for a worker and returned{' '}
                                <span className="mono">202 queued</span> right
                                away. Learn its outcome by{' '}
                                <strong>polling</strong> (
                                <span className="mono">pollRun</span> /{' '}
                                <span className="mono">runAsync</span>),{' '}
                                <strong>streaming</strong> its trace events over
                                Server-Sent Events (
                                <span className="mono">streamRun</span>), or
                                registering a <strong>webhook</strong> endpoint
                                that MAAC posts each lifecycle event to with an
                                HMAC signature you verify. Every mode produces
                                the same trace, audit, quota, cost, and
                                retention data as a synchronous run.
                            </p>
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: RUNTIME_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: RUNTIME_PHP,
                                    },
                                ]}
                            />
                            <div style={{ marginTop: 12 }}>
                                <Btn
                                    variant="default"
                                    size="sm"
                                    icon="send"
                                    onClick={() => go('webhooks')}
                                >
                                    Manage webhook endpoints
                                </Btn>
                            </div>
                        </DocSection>

                        <DocSection
                            id="server-tools"
                            title="Server-side tools"
                            icon="layers"
                            sub="MAAC-hosted, remote HTTP, and MCP connector tools MAAC runs for you"
                        >
                            <p
                                style={{
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: 'var(--text-2)',
                                    marginTop: 0,
                                }}
                            >
                                A tool's <strong>execution mode</strong> decides
                                who runs it. <strong>Client-side</strong> tools
                                run in your application (you register a handler
                                and report it) — they are the only tools in{' '}
                                <span className="mono">manifest.tools</span> and{' '}
                                <span className="mono">agent.tools</span>.{' '}
                                <strong>Server-side</strong> tools —{' '}
                                <span className="mono">hosted</span>,{' '}
                                <span className="mono">http</span> (an
                                allowlisted external endpoint MAAC calls), and{' '}
                                <span className="mono">connector</span> (a
                                registered MCP server MAAC connects to) — run
                                inside MAAC. You implement{' '}
                                <strong>nothing</strong> for them; the manifest
                                surfaces them per agent as{' '}
                                <span className="mono">server_tools</span> so
                                you can see what an agent uses end-to-end. They
                                follow the same schema, governance/approval,
                                quota, trace, and audit standards as every other
                                tool.
                            </p>
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: SERVER_TOOLS_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: SERVER_TOOLS_PHP,
                                    },
                                ]}
                            />
                            <div style={{ marginTop: 12 }}>
                                <Btn
                                    variant="default"
                                    size="sm"
                                    icon="layers"
                                    onClick={() => go('connectors')}
                                >
                                    Manage MCP connectors
                                </Btn>
                            </div>
                        </DocSection>

                        <DocSection
                            id="handlers"
                            title="Implementing a handler"
                            icon="code"
                            sub="The application's own business logic and data access live here"
                        >
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: HANDLER_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: HANDLER_PHP,
                                    },
                                    {
                                        value: 'py',
                                        label: 'Python',
                                        lang: 'python',
                                        code: HANDLER_PY,
                                    },
                                ]}
                            />
                            <p
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                    marginTop: 12,
                                    marginBottom: 0,
                                }}
                            >
                                MAAC generates ready-to-paste stubs like these
                                from each tool contract — copy them from a
                                tool's panel in the SDK Implementation Center.
                            </p>
                        </DocSection>

                        <DocSection
                            id="versioning"
                            title="Versioning & compatibility"
                            icon="layers"
                            sub="Detect an incompatible SDK build before invoking anything"
                        >
                            <p
                                style={{
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: 'var(--text-2)',
                                    marginTop: 0,
                                }}
                            >
                                Every response carries an{' '}
                                <span className="mono">X-Maac-Api-Version</span>{' '}
                                header, and{' '}
                                <span className="mono">GET /api/v1/sdk</span>{' '}
                                negotiates your installed client version into{' '}
                                <span className="mono">compatible</span> /{' '}
                                <span className="mono">upgrade_required</span> /{' '}
                                <span className="mono">ahead</span> /{' '}
                                <span className="mono">unknown</span>. The SDK
                                wraps it as{' '}
                                <span className="mono">
                                    client.compatibility()
                                </span>
                                .
                            </p>
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: COMPAT_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: COMPAT_PHP,
                                    },
                                ]}
                            />
                            <div style={{ marginTop: 12 }}>
                                <Btn
                                    variant="default"
                                    size="sm"
                                    icon="link"
                                    onClick={() => go('sdk')}
                                >
                                    Open the compatibility dashboard
                                </Btn>
                            </div>
                        </DocSection>

                        <DocSection
                            id="validate"
                            title="Validate before reporting"
                            icon="shield"
                            sub="Catch contract drift in your CI, not at runtime"
                        >
                            <p
                                style={{
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: 'var(--text-2)',
                                    marginTop: 0,
                                }}
                            >
                                The SDK ships a{' '}
                                <span className="mono">ToolTester</span> that
                                checks a handler's <b>input and output</b>{' '}
                                against the contract schema — the same rules
                                MAAC enforces at runtime (a mismatch is rejected
                                with{' '}
                                <span className="mono">
                                    invalid_tool_result
                                </span>
                                ). Run it before you report a handler as
                                implemented.
                            </p>
                            <CodeTabs
                                samples={[
                                    {
                                        value: 'ts',
                                        label: 'TypeScript',
                                        lang: 'typescript',
                                        code: VALIDATE_TS,
                                    },
                                    {
                                        value: 'php',
                                        label: 'PHP',
                                        lang: 'php',
                                        code: VALIDATE_PHP,
                                    },
                                ]}
                            />
                        </DocSection>

                        <DocSection
                            id="errors"
                            title="Error handling"
                            icon="alert"
                            sub="Every controlled failure is a typed error carrying MAAC's code + HTTP status"
                        >
                            <Table
                                columns={[
                                    { label: 'Code' },
                                    { label: 'HTTP' },
                                    { label: 'Meaning / fix' },
                                ]}
                            >
                                {ERRORS.map((e) => (
                                    <Tr key={e.code}>
                                        <Td mono strong>
                                            {e.code}
                                        </Td>
                                        <Td>
                                            <Badge
                                                tone={
                                                    e.http < 400
                                                        ? 'teal'
                                                        : e.http < 500
                                                          ? 'amber'
                                                          : 'red'
                                                }
                                            >
                                                {e.http}
                                            </Badge>
                                        </Td>
                                        <Td style={{ color: 'var(--text-2)' }}>
                                            {e.meaning}
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                            <p
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                    marginTop: 12,
                                    marginBottom: 0,
                                }}
                            >
                                Two SDK-side errors surface integration mistakes
                                early:{' '}
                                <span className="mono">
                                    MissingToolHandlerError
                                </span>{' '}
                                (MAAC paused for a tool you didn't register) and{' '}
                                <span className="mono">
                                    RunNotResolvedError
                                </span>{' '}
                                (the run never reached a terminal state in the
                                loop budget).
                            </p>
                        </DocSection>

                        <DocSection
                            id="matrix"
                            title="Compatibility matrix"
                            icon="grid"
                            sub="API contract v0.0.1 — SDK package versions track the contract MAJOR"
                        >
                            <Table
                                columns={[
                                    { label: 'SDK / stack' },
                                    { label: 'Version' },
                                    { label: 'Status' },
                                    { label: 'Notes' },
                                ]}
                            >
                                {MATRIX.map((m) => (
                                    <Tr key={m.name}>
                                        <Td strong>{m.name}</Td>
                                        <Td mono>{m.version}</Td>
                                        <Td>
                                            <Badge tone={m.tone}>
                                                {m.status}
                                            </Badge>
                                        </Td>
                                        <Td style={{ color: 'var(--text-2)' }}>
                                            {m.notes}
                                        </Td>
                                    </Tr>
                                ))}
                            </Table>
                            <p
                                style={{
                                    fontSize: 12.5,
                                    color: 'var(--text-3)',
                                    marginTop: 12,
                                    marginBottom: 0,
                                }}
                            >
                                Every supported SDK language passes the same
                                shared contract fixture suite (
                                <span className="mono">
                                    packages/sdk-fixtures
                                </span>
                                ), so they decide schema validity,
                                compatibility, and error handling identically.
                            </p>
                        </DocSection>

                        <DocSection
                            id="troubleshooting"
                            title="Troubleshooting"
                            icon="search"
                            sub="The most common integration snags"
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                }}
                            >
                                {TROUBLESHOOT.map((t) => (
                                    <div key={t.q}>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                fontWeight: 700,
                                                fontFamily: 'var(--font-mono)',
                                                color: 'var(--text)',
                                            }}
                                        >
                                            {t.q}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: 'var(--text-2)',
                                                lineHeight: 1.55,
                                                marginTop: 2,
                                            }}
                                        >
                                            {t.a}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </DocSection>

                        <DocSection
                            id="reference"
                            title="Reference apps & further reading"
                            icon="book"
                            sub="Copy-paste starting points and the deeper guides"
                        >
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 10,
                                }}
                            >
                                {[
                                    {
                                        code: 'reference-apps/laravel-consumer',
                                        desc: 'Idiomatic Laravel wiring — service provider + Artisan command.',
                                    },
                                    {
                                        code: 'reference-apps/php-cli-consumer',
                                        desc: 'Plain-PHP CLI — no framework.',
                                    },
                                    {
                                        code: 'reference-apps/node-consumer',
                                        desc: 'Node / TypeScript — a non-PHP stack.',
                                    },
                                    {
                                        code: 'packages/maac-sdk-php/examples',
                                        desc: 'PHP simple + advanced examples.',
                                    },
                                    {
                                        code: 'packages/maac-sdk-ts/examples',
                                        desc: 'TypeScript simple + advanced examples.',
                                    },
                                    {
                                        code: 'docs/MAAC_SDK_Migration_Guide.md',
                                        desc: 'Versioning policy, deprecation windows, upgrade steps.',
                                    },
                                ].map((r) => (
                                    <div
                                        key={r.code}
                                        style={{
                                            border: '1px solid var(--border)',
                                            borderRadius: 9,
                                            padding: '10px 12px',
                                        }}
                                    >
                                        <div
                                            className="mono"
                                            style={{
                                                fontSize: 12,
                                                fontWeight: 700,
                                                color: 'var(--primary)',
                                            }}
                                        >
                                            {r.code}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: 'var(--text-2)',
                                                marginTop: 3,
                                                lineHeight: 1.5,
                                            }}
                                        >
                                            {r.desc}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </DocSection>

                        {/* Scroll room so the last sections can reach the
                            reading line and the active TOC item tracks them. */}
                        <div aria-hidden="true" style={{ height: '55vh' }} />
                    </div>
                </div>
            </div>
        </>
    );
}
