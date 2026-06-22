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
    */

    'runtime' => [
        'driver' => env('MAAC_LLM_DRIVER', 'ai'),
        'max_steps' => (int) env('MAAC_RUNTIME_MAX_STEPS', 8),
        'default_timeout_seconds' => (int) env('MAAC_RUNTIME_TIMEOUT', 120),
        'per_turn_timeout_seconds' => (int) env('MAAC_RUNTIME_TURN_TIMEOUT', 30),
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
        'api_version' => env('MAAC_SDK_API_VERSION', '1.0.0'),

        'minimum_client_version' => env('MAAC_SDK_MIN_CLIENT_VERSION', '1.0.0'),

        'current_client_version' => env('MAAC_SDK_CURRENT_CLIENT_VERSION', '1.0.0'),

        'packages' => [
            'php' => [
                'name' => 'milaha/maac-sdk',
                'version' => '1.0.0',
                'registry' => 'packagist',
                'status' => 'supported',
            ],
            'typescript' => [
                'name' => '@maac/sdk',
                'version' => '1.0.0',
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
