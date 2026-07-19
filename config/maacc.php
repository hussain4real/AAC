<?php

use App\Support\Governance\FilesystemAuditArchive;
use App\Support\Secrets\DatabaseSecretVault;

return [

    /*
    |--------------------------------------------------------------------------
    | Enterprise Readiness Containment
    |--------------------------------------------------------------------------
    |
    | MAACC remains non-enterprise until the readiness gates are signed. New
    | account and tenant creation therefore fail closed unless an operator has
    | explicitly enabled a controlled onboarding window. The status message is
    | shared with every Inertia surface so the containment state is visible.
    |
    */

    'readiness' => [
        'asset_manifest' => env('MAACC_ASSET_MANIFEST', public_path('build/manifest.json')),
        'status' => env('MAACC_ENTERPRISE_STATUS', 'non_enterprise'),
        'message' => env(
            'MAACC_ENTERPRISE_STATUS_MESSAGE',
            'Enterprise onboarding and real sensitive-data onboarding are paused pending readiness approval.',
        ),
        'registration_enabled' => (bool) env('MAACC_REGISTRATION_ENABLED', false),
        'team_creation_enabled' => (bool) env('MAACC_TEAM_CREATION_ENABLED', false),
        'real_sensitive_data_enabled' => (bool) env('MAACC_REAL_SENSITIVE_DATA_ENABLED', false),
        'change_owner' => env('MAACC_READINESS_CHANGE_OWNER', 'Aminu Hussain'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unified Outbound Request Policy
    |--------------------------------------------------------------------------
    |
    | Every tenant-controlled HTTP destination is resolved, classified and
    | pinned immediately before connection. Automatic redirects are disabled;
    | approved redirects are followed only after the next hop passes the same
    | policy. Production permits HTTPS on approved ports only.
    |
    */

    'outbound' => [
        'require_https' => false,
        'infrastructure_enforced' => (bool) env('MAACC_EGRESS_INFRASTRUCTURE_ENFORCED', false),
        'connect_timeout_seconds' => (int) env('MAACC_OUTBOUND_CONNECT_TIMEOUT', 3),
        'timeout_seconds' => (int) env('MAACC_OUTBOUND_TIMEOUT', 10),
        'max_redirects' => (int) env('MAACC_OUTBOUND_MAX_REDIRECTS', 3),
        'sensitive_headers' => [],
        'purposes' => [
            'remote_http' => [
                'allowed_ports' => [443],
                'require_allowlist' => true,
                'allowed_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('MAACC_REMOTE_HTTP_ALLOWED_HOSTS', '')),
                ))),
            ],
            'webhook' => [
                'allowed_ports' => [443],
            ],
            'sso' => [
                'allowed_ports' => [443],
            ],
            'mcp' => [
                'allowed_ports' => [443],
            ],
            'knowledge' => [
                'allowed_ports' => [443],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Runtime
    |--------------------------------------------------------------------------
    |
    | Settings for the MAACC agent run lifecycle. `driver` selects the LLM Router
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
    | the per-call timeout (seconds) MAACC waits on a remote MCP server.
    |
    | `knowledge` configures knowledge-retrieval (RAG) tools: `chunk_size` is the
    | maximum number of words per indexed chunk, and `default_top_k` /
    | `default_min_score` are the retrieval defaults (number of chunks returned and
    | the minimum query-term coverage, 0–1) when a tool does not set its own.
    |
    */

    'runtime' => [
        'policy_version' => env('MAACC_RUNTIME_POLICY_VERSION', '1.0.0'),
        'api_weight_budget_per_minute' => (int) env('MAACC_API_WEIGHT_BUDGET_PER_MINUTE', 120),
        'api_concurrency' => [
            'run' => (int) env('MAACC_API_RUN_CONCURRENCY', 2),
            'callback' => (int) env('MAACC_API_CALLBACK_CONCURRENCY', 4),
            'write' => (int) env('MAACC_API_WRITE_CONCURRENCY', 5),
            'read' => (int) env('MAACC_API_READ_CONCURRENCY', 10),
        ],
        'driver' => env('MAACC_LLM_DRIVER', 'ai'),
        'max_steps' => (int) env('MAACC_RUNTIME_MAX_STEPS', 8),
        'default_timeout_seconds' => (int) env('MAACC_RUNTIME_TIMEOUT', 120),
        'per_turn_timeout_seconds' => (int) env('MAACC_RUNTIME_TURN_TIMEOUT', 30),
        'verify_timeout_seconds' => (int) env('MAACC_VERIFY_TIMEOUT', 15),
        'state_store' => env('MAACC_RUNTIME_STATE_STORE'),
        'state_ttl_seconds' => (int) env('MAACC_RUNTIME_STATE_TTL', 300),

        'stream' => [
            'poll_interval_ms' => (int) env('MAACC_RUNTIME_STREAM_INTERVAL', 500),
            'max_seconds' => (int) env('MAACC_RUNTIME_STREAM_MAX_SECONDS', 60),
            'max_concurrent_per_application' => (int) env('MAACC_RUNTIME_STREAM_CONCURRENCY', 5),
        ],

        'gateway' => [
            'max_body_kb' => (int) env('MAACC_GATEWAY_MAX_BODY_KB', 11264),
            'max_header_kb' => (int) env('MAACC_GATEWAY_MAX_HEADER_KB', 16),
            'request_timeout_seconds' => (int) env('MAACC_GATEWAY_REQUEST_TIMEOUT', 150),
        ],

        'webhooks' => [
            'timeout_seconds' => (int) env('MAACC_WEBHOOK_TIMEOUT', 10),
            'connect_timeout_seconds' => (int) env('MAACC_WEBHOOK_CONNECT_TIMEOUT', 3),
            'max_attempts' => (int) env('MAACC_WEBHOOK_MAX_ATTEMPTS', 5),
            'backoff' => [10, 30, 60, 120],
            'signature_tolerance_seconds' => (int) env('MAACC_WEBHOOK_SIGNATURE_TOLERANCE', 300),
            'secret_rotation_overlap_seconds' => (int) env('MAACC_WEBHOOK_SECRET_OVERLAP', 86400),
            'retention_days' => (int) env('MAACC_WEBHOOK_RETENTION_DAYS', 30),
            'stale_claim_seconds' => (int) env('MAACC_WEBHOOK_STALE_CLAIM_SECONDS', 180),
        ],

        'remote_http' => [
            'blocked_hosts' => [
                'localhost',
                '127.0.0.1',
                '0.0.0.0',
                '::1',
                '169.254.169.254',
                'metadata.google.internal',
            ],
            'max_attempts' => (int) env('MAACC_REMOTE_HTTP_MAX_ATTEMPTS', 3),
            'connect_timeout_seconds' => (int) env('MAACC_REMOTE_HTTP_CONNECT_TIMEOUT', 5),
        ],

        'mcp' => [
            'timeout_seconds' => (int) env('MAACC_MCP_TIMEOUT', 20),
        ],

        'knowledge' => [
            'chunk_size' => (int) env('MAACC_KNOWLEDGE_CHUNK_SIZE', 120),
            'default_top_k' => (int) env('MAACC_KNOWLEDGE_TOP_K', 5),
            'default_min_score' => (float) env('MAACC_KNOWLEDGE_MIN_SCORE', 0.1),

            // Direct document upload: the user-assigned extensions accepted by
            // the ingest endpoint (the extractor reads them from storage) and
            // the max upload size in kilobytes.
            'upload' => [
                'allowed_extensions' => ['txt', 'md', 'markdown', 'csv', 'pdf', 'docx'],
                'max_kb' => (int) env('MAACC_KNOWLEDGE_UPLOAD_MAX_KB', 10240),
                'max_archive_entries' => (int) env('MAACC_KNOWLEDGE_MAX_ARCHIVE_ENTRIES', 500),
                'max_decompressed_kb' => (int) env('MAACC_KNOWLEDGE_MAX_DECOMPRESSED_KB', 51200),
                'max_compression_ratio' => (int) env('MAACC_KNOWLEDGE_MAX_COMPRESSION_RATIO', 100),
                'max_pdf_pages' => (int) env('MAACC_KNOWLEDGE_MAX_PDF_PAGES', 500),
                'max_text_characters' => (int) env('MAACC_KNOWLEDGE_MAX_TEXT_CHARACTERS', 1000000),
                'max_chunks' => (int) env('MAACC_KNOWLEDGE_MAX_CHUNKS', 5000),
                // Set this to an independently managed ClamAV-compatible binary
                // in production. Production ingestion fails closed when absent.
                'malware_scanner_binary' => env('MAACC_MALWARE_SCANNER_BINARY'),
                'scanner_timeout_seconds' => (int) env('MAACC_MALWARE_SCANNER_TIMEOUT', 60),
            ],
        ],

        // `db` configures governed read-only database tools: `default_row_limit`
        // is the per-query row cap applied when a tool does not set its own (and
        // is itself bounded by the data source's hard `max_rows`). Read-only `db`
        // tools query only approved, ops-provisioned read-only connections
        // (replicas / reporting schemas) referenced by name; MAACC never persists
        // a connection string and resolves any injected credential from the vault.
        // `allowed_connections` is the allowlist of `config/database.php`
        // connection names a data source may reference — it blocks pointing a
        // data source at MAACC's own operational database or any unapproved
        // connection. An empty allowlist blocks every connection (safe default).
        'db' => [
            'default_row_limit' => (int) env('MAACC_DB_DEFAULT_ROW_LIMIT', 50),
            'allowed_connections' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('MAACC_DB_ALLOWED_CONNECTIONS', 'maacc_reporting')),
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
        'currency' => 'USD',

        'unit' => 'per_million_tokens',

        'source' => 'MAACC governed catalog',

        'version' => '2026-07-19',

        'effective_at' => '2026-07-19T00:00:00Z',

        'models' => [
            'gpt-5.4' => ['input' => 1.25, 'output' => 10.0],
        ],

        'max_rate_per_million' => (float) env('MAACC_PRICING_MAX_RATE', 1000.0),
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
    | v1 response (`X-Maacc-Api-Version`), in the manifest, and at `GET
    | /api/v1/sdk`, so a client can detect whether MAACC speaks a contract it
    | understands. A breaking response-shape change bumps the major — and the
    | shared contract fixtures (packages/sdk-fixtures) fail CI until every
    | supported SDK language is updated to match.
    |
    | `minimum_client_version` is the oldest SDK package version MAACC still
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
        'api_version' => env('MAACC_SDK_API_VERSION', '1.0.0'),

        'minimum_client_version' => env('MAACC_SDK_MIN_CLIENT_VERSION', '1.0.0'),

        'current_client_version' => env('MAACC_SDK_CURRENT_CLIENT_VERSION', '1.0.0'),

        'packages' => [
            'php' => [
                'name' => 'maacc/sdk',
                'version' => '1.0.0',
                'registry' => 'composer-vcs',
                'status' => 'supported',
            ],
            'typescript' => [
                'name' => '@qatar-navigation-milaha/sdk',
                'version' => '1.0.0',
                'registry' => 'npm',
                'status' => 'supported',
            ],
            'python' => [
                'name' => 'maacc-sdk',
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
            [
                'id' => 'requires-tool-persisted-status',
                'summary' => '`requires_tool` is a transient model decision; consume the durable `waiting_for_client` pause instead.',
                'deprecated_in' => '1.0.0',
                'removed_in' => '2.0.0',
                'guide' => '/docs/MAACC_SDK_Migration_Guide.md#v1-run-status-migration',
            ],
            [
                'id' => 'legacy-compact-schema-strings',
                'summary' => 'Legacy `field => type` strings remain accepted in v1; new contracts should use the versioned rich compact dialect.',
                'deprecated_in' => '1.0.0',
                'removed_in' => '2.0.0',
                'guide' => '/docs/MAACC_SDK_Migration_Guide.md#v1-schema-migration',
            ],
        ],
    ],

    'caller_context' => [
        'signing_key' => env('MAACC_CALLER_CONTEXT_SIGNING_KEY', env('APP_KEY')),
        'ttl_seconds' => (int) env('MAACC_CALLER_CONTEXT_TTL', 300),
        'allowed_departments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MAACC_CALLER_CONTEXT_DEPARTMENTS', '')),
        ))),
        'allowed_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MAACC_CALLER_CONTEXT_ROLES', '')),
        ))),
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
        'driver' => env('MAACC_VAULT_DRIVER', DatabaseSecretVault::class),
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
        'health_window_minutes' => (int) env('MAACC_ROUTING_HEALTH_WINDOW', 60),
        'health_min_sample' => (int) env('MAACC_ROUTING_HEALTH_MIN_SAMPLE', 5),
        'health_failure_threshold' => (float) env('MAACC_ROUTING_HEALTH_FAILURE_THRESHOLD', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enterprise Identity (SSO) (Phase 6G)
    |--------------------------------------------------------------------------
    |
    | Settings for the OAuth 2.0 / OIDC authorization-code login flow. `http_timeout_seconds`
    | bounds each outbound call to the provider's token and userinfo endpoints.
    | Rejected attempts and IdP availability failures are aggregated into the
    | tenant alert feed over `alert_window_minutes`; the rejection alert fires at
    | `rejected_login_alert_threshold` attempts inside that window.
    |
    */

    'sso' => [
        'http_timeout_seconds' => (int) env('MAACC_SSO_HTTP_TIMEOUT', 10),
        'connect_timeout_seconds' => (int) env('MAACC_SSO_CONNECT_TIMEOUT', 3),
        'allowed_id_token_algorithms' => ['RS256'],
        'flow_ttl_seconds' => (int) env('MAACC_SSO_FLOW_TTL', 600),
        'alert_window_minutes' => (int) env('MAACC_SSO_ALERT_WINDOW', 15),
        'rejected_login_alert_threshold' => (int) env('MAACC_SSO_REJECTED_ALERT_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit integrity and independent archive
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'chain_key_id' => env('MAACC_AUDIT_CHAIN_KEY_ID', 'local-chain-v1'),
        'chain_key' => env('MAACC_AUDIT_CHAIN_KEY'),
        'chain_verification_keys' => json_decode((string) env('MAACC_AUDIT_CHAIN_PREVIOUS_KEYS', '{}'), true) ?: [],
        'export_key_id' => env('MAACC_AUDIT_EXPORT_KEY_ID', 'local-export-v1'),
        'export_key' => env('MAACC_AUDIT_EXPORT_KEY'),
        'export_verification_keys' => json_decode((string) env('MAACC_AUDIT_EXPORT_PREVIOUS_KEYS', '{}'), true) ?: [],
        'archive_disk' => env('MAACC_AUDIT_ARCHIVE_DISK', 'audit_archive'),
        'archive_immutable_enforced' => (bool) env('MAACC_AUDIT_ARCHIVE_IMMUTABLE_ENFORCED', false),
        'archive_retention_days' => (int) env('MAACC_AUDIT_ARCHIVE_RETENTION_DAYS', 2555),
        'archive_driver' => FilesystemAuditArchive::class,
    ],

    'governance' => [
        'approval_ttl_hours' => (int) env('MAACC_APPROVAL_TTL_HOURS', 168),
        'quarantine_retention_days' => (int) env('MAACC_QUARANTINE_RETENTION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Administration (Phase 8B)
    |--------------------------------------------------------------------------
    |
    | MAACC's own internal administration model (Spatie roles/permissions). These
    | global platform roles ({@see \App\Enums\PlatformRole}) gate cross-tenant
    | MAACC administration and are distinct from the team/project-scoped tenant
    | RBAC. Tenant users hold no platform role by default.
    |
    | `super_admins` bootstraps MAACC Super Admins by email so the platform is
    | never left without an administrator — empty in production by default;
    | assign platform roles through the console or SSO rather than auto-granting.
    | `break_glass` bounds emergency access grants (a default and a hard-max TTL
    | in minutes). `access_review` configures the re-certification window for a
    | standard grant and the inactivity window after which a platform admin is
    | flagged stale.
    |
    */

    'platform' => [
        'super_admins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MAACC_PLATFORM_SUPER_ADMINS', '')),
        ))),

        'break_glass' => [
            'default_ttl_minutes' => (int) env('MAACC_BREAK_GLASS_TTL', 60),
            'max_ttl_minutes' => (int) env('MAACC_BREAK_GLASS_MAX_TTL', 240),
        ],

        'access_review' => [
            'certification_days' => (int) env('MAACC_ACCESS_CERTIFICATION_DAYS', 90),
            'stale_days' => (int) env('MAACC_ACCESS_STALE_DAYS', 60),
        ],
    ],

];
