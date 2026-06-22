<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MAAC connection
    |--------------------------------------------------------------------------
    |
    | The base URL of the MAAC instance and the application credential issued in
    | the MAAC console (Applications → Credentials → Generate). The secret is
    | shown only once on generation/rotation, so store it like any other secret.
    |
    */

    'base_url' => env('MAAC_BASE_URL', 'https://maac.test'),
    'client_id' => env('MAAC_CLIENT_ID', ''),
    'client_secret' => env('MAAC_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Agent + tool mapping
    |--------------------------------------------------------------------------
    |
    | The published agent this app invokes, and the mapping from a local handler
    | to the MAAC tool contract slug it implements.
    |
    */

    'agent_slug' => env('MAAC_AGENT_SLUG', 'e2e-ops-agent'),

    'tools' => [
        'fetch_records' => env('MAAC_TOOL_FETCH_RECORDS', 'e2e-fetch-records'),
    ],

];
