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

];
