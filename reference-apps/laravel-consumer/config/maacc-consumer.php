<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MAACC connection
    |--------------------------------------------------------------------------
    |
    | The base URL of the MAACC instance and the application credential issued in
    | the MAACC console (Applications → Credentials → Generate). The secret is
    | shown only once on generation/rotation, so store it like any other secret.
    |
    */

    'base_url' => env('MAACC_BASE_URL', 'https://maacc.test'),
    'client_id' => env('MAACC_CLIENT_ID', ''),
    'client_secret' => env('MAACC_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Agent + tool mapping
    |--------------------------------------------------------------------------
    |
    | The published agent this app invokes, and the mapping from a local handler
    | to the MAACC tool contract slug it implements.
    |
    */

    'agent_slug' => env('MAACC_AGENT_SLUG', 'e2e-ops-agent'),

    'tools' => [
        'fetch_records' => env('MAACC_TOOL_FETCH_RECORDS', 'e2e-fetch-records'),
    ],

];
