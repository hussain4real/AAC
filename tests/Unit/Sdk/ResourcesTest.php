<?php

use Maac\Sdk\Resources\Manifest;
use Maac\Sdk\Resources\ManifestTool;
use Maac\Sdk\Resources\Run;
use Maac\Sdk\Resources\ToolCall;

/**
 * Unit coverage for the response value objects: how they parse MAAC's envelopes
 * and the status helpers consumers branch on.
 */
it('parses a waiting run with its pending tool call', function () {
    $run = Run::fromArray([
        'run_id' => 'r1', 'agent_slug' => 'ops', 'status' => 'waiting_for_client',
        'usage' => ['tokens_in' => 3, 'tokens_out' => 0], 'cost' => 0.5,
        'tool_call' => ['id' => 'c1', 'tool' => 'fetch', 'arguments' => ['q' => 'x'], 'output_schema' => ['records' => 'array']],
    ]);

    expect($run->isWaiting())->toBeTrue()
        ->and($run->isCompleted())->toBeFalse()
        ->and($run->isTerminal())->toBeFalse()
        ->and($run->tokensIn)->toBe(3)
        ->and($run->cost)->toBe(0.5)
        ->and($run->toolCall)->toBeInstanceOf(ToolCall::class)
        ->and($run->toolCall?->tool)->toBe('fetch')
        ->and($run->toolCall?->arguments)->toBe(['q' => 'x'])
        ->and($run->toolCall?->outputSchema)->toBe(['records' => 'array']);
});

it('parses a completed run', function () {
    $run = Run::fromArray([
        'run_id' => 'r1', 'agent_slug' => 'ops', 'status' => 'completed',
        'usage' => ['tokens_in' => 3, 'tokens_out' => 4], 'cost' => 1.25, 'response' => 'done',
    ]);

    expect($run->isCompleted())->toBeTrue()
        ->and($run->isTerminal())->toBeTrue()
        ->and($run->response)->toBe('done')
        ->and($run->toolCall)->toBeNull();
});

it('parses a failed run as terminal but not completed', function () {
    $run = Run::fromArray([
        'run_id' => 'r1', 'agent_slug' => 'ops', 'status' => 'failed',
        'usage' => [], 'cost' => 0, 'error' => 'boom',
    ]);

    expect($run->isTerminal())->toBeTrue()
        ->and($run->isCompleted())->toBeFalse()
        ->and($run->error)->toBe('boom')
        ->and($run->tokensIn)->toBe(0);
});

it('defaults a tool call with missing fields', function () {
    $call = ToolCall::fromArray([]);

    expect($call->id)->toBe('')
        ->and($call->tool)->toBe('')
        ->and($call->arguments)->toBe([])
        ->and($call->outputSchema)->toBeNull();
});

it('parses a manifest and looks up agents and tools', function () {
    $manifest = Manifest::fromArray([
        'application' => ['id' => 'cargo', 'name' => 'Cargo', 'environment' => 'staging'],
        'agents' => [
            ['slug' => 'ops', 'name' => 'Ops', 'version' => 'v2', 'status' => 'published', 'tools' => ['fetch', 'lookup']],
            'not-an-array',
        ],
        'tools' => [[
            'name' => 'fetch', 'version' => '1.0.0', 'schema_fingerprint' => 'fp',
            'input_schema' => ['q' => 'string'], 'output_schema' => ['r' => 'array'],
            'implementation' => ['status' => 'implemented', 'handler_name' => 'H', 'implemented_version' => '1.0.0'],
        ]],
    ]);

    expect($manifest->environment)->toBe('staging')
        ->and($manifest->agents)->toHaveCount(1)
        ->and($manifest->agent('ops')?->tools)->toBe(['fetch', 'lookup'])
        ->and($manifest->agent('missing'))->toBeNull()
        ->and($manifest->tool('fetch'))->toBeInstanceOf(ManifestTool::class)
        ->and($manifest->tool('fetch')?->isImplemented())->toBeTrue()
        ->and($manifest->tool('missing'))->toBeNull();
});

it('defaults a manifest tool implementation status to required', function () {
    $tool = ManifestTool::fromArray(['name' => 'fetch', 'version' => '1.0.0', 'schema_fingerprint' => 'fp']);

    expect($tool->implementationStatus())->toBe('required')
        ->and($tool->isImplemented())->toBeFalse()
        ->and($tool->inputSchema)->toBe([])
        ->and($tool->outputSchema)->toBe([]);
});
