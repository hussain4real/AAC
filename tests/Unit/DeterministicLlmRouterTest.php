<?php

use App\Support\Runtime\DeterministicLlmRouter;
use App\Support\Runtime\LlmMessage;
use App\Support\Runtime\LlmRequest;
use App\Support\Runtime\LlmToolDefinition;
use App\Support\Sdk\ToolSchema;

/**
 * Build an {@see LlmRequest} with the given tools and messages.
 *
 * @param  array<int, LlmToolDefinition>  $tools
 * @param  array<int, LlmMessage>  $messages
 */
function deterministicRequest(array $tools = [], array $messages = [new LlmMessage('user', 'hi')]): LlmRequest
{
    return new LlmRequest(
        providerDriver: 'fake',
        modelCode: 'fake/e2e',
        systemPrompt: 'You are deterministic.',
        messages: $messages,
        tools: $tools,
    );
}

test('it answers with final text when the agent exposes no tools', function () {
    $completion = (new DeterministicLlmRouter)->complete(deterministicRequest());

    expect($completion->isToolCall())->toBeFalse()
        ->and($completion->text)->toBe('Deterministic agent response.')
        ->and($completion->usage->tokensIn)->toBe(120)
        ->and($completion->usage->tokensOut)->toBe(48);
});

test('it calls the first tool with a schema-valid payload covering every base type', function () {
    $schema = [
        'name' => 'string',
        'count' => 'integer',
        'ratio' => 'number',
        'active' => 'boolean',
        'meta' => 'object',
        'items' => 'array',
        'note' => 'string?',
    ];
    $tool = new LlmToolDefinition('fetch_records', 'Fetch records', $schema);

    $completion = (new DeterministicLlmRouter)->complete(deterministicRequest([$tool]));

    expect($completion->isToolCall())->toBeTrue()
        ->and($completion->toolName)->toBe('fetch_records')
        // The optional field is omitted; required fields are present and typed.
        ->and($completion->toolArguments)->toBe([
            'name' => 'e2e',
            'count' => 1,
            'ratio' => 1,
            'active' => true,
            'meta' => ['value' => 'e2e'],
            'items' => ['e2e'],
        ])
        ->and(ToolSchema::payloadIsValid($schema, $completion->toolArguments))->toBeTrue();
});

test('it answers with final text once a tool result is in the conversation', function () {
    $tool = new LlmToolDefinition('fetch_records', 'Fetch records', ['query' => 'string']);
    $messages = [
        new LlmMessage('user', 'summarize'),
        LlmMessage::assistant('{"tool":"fetch_records"}'),
        LlmMessage::tool('fetch_records', '{"records":[],"total":0}'),
    ];

    $completion = (new DeterministicLlmRouter)->complete(deterministicRequest([$tool], $messages));

    expect($completion->isToolCall())->toBeFalse()
        ->and($completion->text)->toBe('Deterministic agent response.');
});
