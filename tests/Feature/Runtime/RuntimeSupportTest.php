<?php

use App\Enums\Environment;
use App\Enums\LlmFinishReason;
use App\Enums\LlmStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\LlmProvider;
use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\LlmMessage;
use App\Support\Runtime\LlmRequest;
use App\Support\Runtime\LlmToolDefinition;
use App\Support\Runtime\RunPayload;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

test('the AI router returns a final-text completion for a plain reply', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['All clear.']);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'anthropic',
        modelCode: 'claude-3',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('status?')],
    ));

    expect($completion->finishReason)->toBe(LlmFinishReason::Stop)
        ->and($completion->isToolCall())->toBeFalse()
        ->and($completion->text)->toBe('All clear.')
        ->and($completion->usage->tokensIn)->toBeInt();
});

test('the AI router parses a tool-call envelope across a full transcript', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['{"tool":"lookup","arguments":{"q":"x"}}']);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'anthropic',
        modelCode: 'claude-3',
        systemPrompt: 'You help.',
        messages: [
            LlmMessage::user('find x'),
            LlmMessage::assistant('working on it'),
            LlmMessage::tool('lookup', '{"r":1}'),
        ],
        tools: [new LlmToolDefinition('lookup', 'Looks things up', ['q' => 'string'])],
    ));

    expect($completion->finishReason)->toBe(LlmFinishReason::ToolCall)
        ->and($completion->isToolCall())->toBeTrue()
        ->and($completion->toolName)->toBe('lookup')
        ->and($completion->toolArguments)->toBe(['q' => 'x']);
});

test('the AI router treats an envelope for an unknown tool as plain text', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['{"tool":"ghost","arguments":{}}']);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'anthropic',
        modelCode: 'claude-3',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('hi')],
        tools: [new LlmToolDefinition('lookup', 'Looks things up', ['q' => 'string'])],
    ));

    expect($completion->isToolCall())->toBeFalse()
        ->and($completion->text)->toBe('{"tool":"ghost","arguments":{}}');
});

test('a catalog entry maps to an AI provider driver and falls back to the default', function () {
    expect(LlmProvider::factory()->make(['provider' => 'Anthropic Claude'])->driver())->toBe('anthropic')
        ->and(LlmProvider::factory()->make(['provider' => 'Azure OpenAI'])->driver())->toBe('azure')
        ->and(LlmProvider::factory()->make(['provider' => 'Milaha On-Prem GPU'])->driver())->toBe((string) config('ai.default'));
});

test('a catalog entry reports environment availability', function () {
    $provider = LlmProvider::factory()->make([
        'status' => LlmStatus::Approved,
        'environments' => [Environment::Production->value],
    ]);

    expect($provider->isAvailableIn(Environment::Production->value))->toBeTrue()
        ->and($provider->isAvailableIn(Environment::Staging->value))->toBeFalse();
});

test('the hosted tool registry resolves built-ins and rejects unknown slugs', function () {
    $registry = new HostedToolRegistry;

    expect($registry->has('echo'))->toBeTrue()
        ->and($registry->has('current_time'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->resolve('echo')->handle(['message' => 'hi']))->toBe(['message' => 'hi'])
        ->and($registry->resolve('echo')->handle([]))->toBe(['message' => ''])
        ->and($registry->resolve('current_time')->handle([]))->toHaveKey('iso');

    expect(fn () => $registry->resolve('missing'))->toThrow(RuntimeException::class);
});

test('the run payload omits result fields for non-terminal runs', function () {
    [, $team] = ownerAndTeam();
    $run = AgentRun::factory()->create(['status' => RunStatus::Running]);

    $payload = RunPayload::for($run->load('agent'));

    expect($payload)->toHaveKeys(['run_id', 'status', 'usage', 'cost'])
        ->and($payload)->not->toHaveKeys(['response', 'error', 'tool_call']);
});

test('the run payload returns a null tool call when none is pending', function () {
    $run = AgentRun::factory()->create(['status' => RunStatus::WaitingForClient]);

    expect(RunPayload::for($run->load('agent'))['tool_call'])->toBeNull();
});
