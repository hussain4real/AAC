<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Runtime
    |--------------------------------------------------------------------------
    |
    | Settings for the MAAC agent run lifecycle. `max_steps` caps how many
    | model/tool iterations a single run may take (a loop/retry guard).
    | `default_timeout_seconds` is the wall-clock budget after which a run that
    | has not finished is expired. `per_turn_timeout_seconds` bounds an
    | individual LLM provider call.
    |
    */

    'runtime' => [
        'max_steps' => (int) env('MAAC_RUNTIME_MAX_STEPS', 8),
        'default_timeout_seconds' => (int) env('MAAC_RUNTIME_TIMEOUT', 120),
        'per_turn_timeout_seconds' => (int) env('MAAC_RUNTIME_TURN_TIMEOUT', 30),
    ],

];
