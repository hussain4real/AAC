<?php

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
                'name' => 'milaha/maac-sdk',
                'version' => '0.2.0',
                'registry' => 'packagist',
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

];
