<?php

use App\Support\Secrets\DatabaseSecretVault;

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Runtime
    |--------------------------------------------------------------------------
    |
    | Settings for the MAAC agent run lifecycle. `driver` selects the LLM Router
    | binding: the default `ai` driver calls approved providers through the
    | Laravel AI SDK, while `fake` swaps in a deterministic, dependency-free
    | router so the full run lifecycle can be exercised end-to-end without model
    | spend or network flakiness (used by the validation harness and local
    | smoke runs). `max_steps` caps how many model/tool iterations a single run
    | may take (a loop/retry guard). `default_timeout_seconds` is the wall-clock
    | budget after which a run that has not finished is expired.
    | `per_turn_timeout_seconds` bounds an individual LLM provider call.
    |
    | `stream` configures the Server-Sent Events runtime feed (the poll interval
    | between trace-event flushes and the wall-clock cap on a single stream).
    | `webhooks` configures outbound run-event delivery: the per-attempt HTTP
    | timeout, the maximum number of attempts, the exponential backoff schedule
    | (seconds per retry), and how much clock skew a receiver may tolerate when
    | verifying a signature.
    |
    | `remote_http` governs egress for remote HTTP tools: `allowed_hosts` is the
    | allowlist a tool endpoint host must match (supports `*.` wildcards) — an
    | empty allowlist blocks every remote HTTP tool, which is the safe default;
    | `blocked_hosts` is a denylist (loopback/link-local/metadata) that overrides
    | the allowlist as SSRF defense-in-depth; `max_attempts` caps per-tool retry,
    | and `connect_timeout_seconds` bounds the TCP connect.
    |
    | `mcp` configures the outbound MCP client used for connector-backed tools:
    | the per-call timeout (seconds) MAAC waits on a remote MCP server.
    |
    | `knowledge` configures knowledge-retrieval (RAG) tools: `chunk_size` is the
    | maximum number of words per indexed chunk, and `default_top_k` /
    | `default_min_score` are the retrieval defaults (number of chunks returned and
    | the minimum query-term coverage, 0–1) when a tool does not set its own.
    |
    */

    'runtime' => [
        'driver' => env('MAAC_LLM_DRIVER', 'ai'),
        'max_steps' => (int) env('MAAC_RUNTIME_MAX_STEPS', 8),
        'default_timeout_seconds' => (int) env('MAAC_RUNTIME_TIMEOUT', 120),
        'per_turn_timeout_seconds' => (int) env('MAAC_RUNTIME_TURN_TIMEOUT', 30),

        'stream' => [
            'poll_interval_ms' => (int) env('MAAC_RUNTIME_STREAM_INTERVAL', 500),
            'max_seconds' => (int) env('MAAC_RUNTIME_STREAM_MAX_SECONDS', 60),
        ],

        'webhooks' => [
            'timeout_seconds' => (int) env('MAAC_WEBHOOK_TIMEOUT', 10),
            'max_attempts' => (int) env('MAAC_WEBHOOK_MAX_ATTEMPTS', 5),
            'backoff' => [10, 30, 60, 120],
            'signature_tolerance_seconds' => (int) env('MAAC_WEBHOOK_SIGNATURE_TOLERANCE', 300),
        ],

        'remote_http' => [
            'allowed_hosts' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MAAC_REMOTE_HTTP_ALLOWED_HOSTS', '')),
            ))),
            'blocked_hosts' => [
                'localhost',
                '127.0.0.1',
                '0.0.0.0',
                '::1',
                '169.254.169.254',
                'metadata.google.internal',
            ],
            'max_attempts' => (int) env('MAAC_REMOTE_HTTP_MAX_ATTEMPTS', 3),
            'connect_timeout_seconds' => (int) env('MAAC_REMOTE_HTTP_CONNECT_TIMEOUT', 5),
        ],

        'mcp' => [
            'timeout_seconds' => (int) env('MAAC_MCP_TIMEOUT', 20),
        ],

        'knowledge' => [
            'chunk_size' => (int) env('MAAC_KNOWLEDGE_CHUNK_SIZE', 120),
            'default_top_k' => (int) env('MAAC_KNOWLEDGE_TOP_K', 5),
            'default_min_score' => (float) env('MAAC_KNOWLEDGE_MIN_SCORE', 0.1),

            // Direct document upload: the user-assigned extensions accepted by
            // the ingest endpoint (the extractor reads them from storage) and
            // the max upload size in kilobytes.
            'upload' => [
                'allowed_extensions' => ['txt', 'md', 'markdown', 'csv', 'pdf', 'docx'],
                'max_kb' => (int) env('MAAC_KNOWLEDGE_UPLOAD_MAX_KB', 10240),
            ],
        ],

        // `db` configures governed read-only database tools: `default_row_limit`
        // is the per-query row cap applied when a tool does not set its own (and
        // is itself bounded by the data source's hard `max_rows`). Read-only `db`
        // tools query only approved, ops-provisioned read-only connections
        // (replicas / reporting schemas) referenced by name; MAAC never persists
        // a connection string and resolves any injected credential from the vault.
        // `allowed_connections` is the allowlist of `config/database.php`
        // connection names a data source may reference — it blocks pointing a
        // data source at MAAC's own operational database or any unapproved
        // connection. An empty allowlist blocks every connection (safe default).
        'db' => [
            'default_row_limit' => (int) env('MAAC_DB_DEFAULT_ROW_LIMIT', 50),
            'allowed_connections' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MAAC_DB_ALLOWED_CONNECTIONS', 'maac_reporting')),
            ))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Pricing (cost estimation)
    |--------------------------------------------------------------------------
    |
    | The single reviewed source of truth for model pricing, in US dollars per
    | 1,000,000 tokens — the unit every provider publishes, so a maintainer copies
    | the published figure verbatim (no error-prone per-1K conversion). The
    | runtime ESTIMATES a run's cost as `tokens / 1e6 * rate`; a model with no
    | catalog entry falls back to the per-1M `input_cost`/`output_cost` stored on
    | its catalog row (for custom/on-prem models). Cost is always an estimate:
    | providers return token *usage* via their API, never a per-request dollar
    | amount, so any dollar figure is usage multiplied by this table. Keep these
    | current with the provider's published pricing.
    |
    | @var array<string, array{input: float, output: float}>
    |
    | `max_rate_per_million` is a units-error guardrail (a per-1M rate above it is
    | almost certainly a per-1K figure entered by mistake); a test asserts every
    | catalog rate stays under it.
    |
    */

    'pricing' => [
        'models' => [
            'gpt-5.4' => ['input' => 1.25, 'output' => 10.0],
        ],

        'max_rate_per_million' => (float) env('MAAC_PRICING_MAX_RATE', 1000.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK Distribution, Versioning & Compatibility (Phase 6C)
    |--------------------------------------------------------------------------
    |
    | Turns the SDK/runtime surfaces into a versioned integration product.
    |
    | `api_version` is the semantic version of the SDK/runtime API *contract
    | shape* (the `/api/v1/*` envelopes + the manifest). It is surfaced on every
    | v1 response (`X-Maac-Api-Version`), in the manifest, and at `GET
    | /api/v1/sdk`, so a client can detect whether MAAC speaks a contract it
    | understands. A breaking response-shape change bumps the major — and the
    | shared contract fixtures (packages/sdk-fixtures) fail CI until every
    | supported SDK language is updated to match.
    |
    | `minimum_client_version` is the oldest SDK package version MAAC still
    | supports; `current_client_version` is the latest published one. Together
    | they let a consumer detect whether its installed SDK is compatible,
    | needs upgrading, or is ahead of the server.
    |
    | `packages` is the published-client registry (name, version, support tier)
    | per language. `deprecations` lists contract/SDK deprecations with their
    | removal window and a migration-guide anchor, surfaced on the compatibility
    | dashboard before the change is deployed.
    |
    */

    'sdk' => [
        'api_version' => env('MAAC_SDK_API_VERSION', '0.0.1'),

        'minimum_client_version' => env('MAAC_SDK_MIN_CLIENT_VERSION', '0.0.1'),

        'current_client_version' => env('MAAC_SDK_CURRENT_CLIENT_VERSION', '0.2.0'),

        'packages' => [
            'php' => [
                'name' => 'maac/sdk',
                'version' => '0.2.0',
                'registry' => 'composer-vcs',
                'status' => 'supported',
            ],
            'typescript' => [
                'name' => '@maac/sdk',
                'version' => '0.2.0',
                'registry' => 'npm',
                'status' => 'supported',
            ],
            'python' => [
                'name' => 'maac-sdk',
                'version' => null,
                'registry' => 'pypi',
                'status' => 'experimental',
            ],
        ],

        /*
        | Each entry: id, summary, deprecated_in, removed_in, guide. Empty while
        | the v1 contract is current; populated when a contract change enters a
        | deprecation window so the dashboard can surface it before removal.
        |
        | @var array<int, array{id: string, summary: string, deprecated_in: string, removed_in: string, guide: string}>
        */
        'deprecations' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Secrets Vault (Phase 6G)
    |--------------------------------------------------------------------------
    |
    | The platform secrets vault is the governed system of record for sensitive
    | credential material — approved LLM provider keys, application credentials,
    | remote HTTP tool secrets, webhook signing secrets, and MCP connector
    | credentials. `driver` is the bound implementation of the SecretVault
    | contract; the default database driver encrypts material at rest. An
    | enterprise deployment points this at an external vault driver (e.g. one
    | backed by HashiCorp Vault or AWS Secrets Manager) without changing any
    | caller, since every consumer depends on the interface.
    |
    */

    'vault' => [
        'driver' => env('MAAC_VAULT_DRIVER', DatabaseSecretVault::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Model Routing (Phase 6G)
    |--------------------------------------------------------------------------
    |
    | Settings for the provider-health signal the model router uses to rank and
    | fail over between candidate models. `health_window_minutes` is the recent
    | window over which model-attributable run failures and latency are measured;
    | `health_min_sample` is the minimum number of recent runs before a provider's
    | failure rate is trusted (below it a provider is treated as healthy); and
    | `health_failure_threshold` is the failure rate (0–1) above which a provider
    | is considered unhealthy and deprioritized in routing.
    |
    */

    'routing' => [
        'health_window_minutes' => (int) env('MAAC_ROUTING_HEALTH_WINDOW', 60),
        'health_min_sample' => (int) env('MAAC_ROUTING_HEALTH_MIN_SAMPLE', 5),
        'health_failure_threshold' => (float) env('MAAC_ROUTING_HEALTH_FAILURE_THRESHOLD', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enterprise Identity (SSO) (Phase 6G)
    |--------------------------------------------------------------------------
    |
    | Settings for the OAuth 2.0 / OIDC authorization-code login flow. `http_timeout_seconds`
    | bounds each outbound call to the provider's token and userinfo endpoints.
    |
    */

    'sso' => [
        'http_timeout_seconds' => (int) env('MAAC_SSO_HTTP_TIMEOUT', 10),
    ],

];
