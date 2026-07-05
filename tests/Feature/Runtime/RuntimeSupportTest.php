<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\LlmFinishReason;
use App\Enums\LlmStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\HostedTools\ProviderHostedToolRegistry;
use App\Support\Runtime\LlmMessage;
use App\Support\Runtime\LlmProviderToolDefinition;
use App\Support\Runtime\LlmRequest;
use App\Support\Runtime\LlmToolDefinition;
use App\Support\Runtime\ModelPricing;
use App\Support\Runtime\RunPayload;
use App\Support\Runtime\RuntimeAgent;
use App\Support\Runtime\RuntimeTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

test('the AI router returns a final-text completion for a plain reply', function () {
    Ai::fakeAgent(RuntimeAgent::class, ['All clear.']);

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
    Ai::fakeAgent(RuntimeAgent::class, ['{"tool":"lookup","arguments":{"q":"x"}}']);

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

test('the AI router applies a vault-resolved key to the provider for the call', function () {
    Ai::fakeAgent(RuntimeAgent::class, ['ok']);
    config(['ai.providers.anthropic.key' => 'env-key']);

    (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'anthropic',
        modelCode: 'claude-3',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('hi')],
        apiKey: 'vault-key',
    ));

    expect(config('ai.providers.anthropic.key'))->toBe('vault-key');
});

test('the AI router treats an envelope for an unknown tool as plain text', function () {
    Ai::fakeAgent(RuntimeAgent::class, ['{"tool":"ghost","arguments":{}}']);

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

test('model pricing resolves per-1M rates from the catalog, falling back to the provider row', function () {
    config(['maacc.pricing.models' => ['gpt-5.4' => ['input' => 1.25, 'output' => 10.0]]]);
    $pricing = new ModelPricing;

    $known = LlmProvider::factory()->make(['code' => 'gpt-5.4', 'input_cost' => 99.0, 'output_cost' => 99.0]);
    $custom = LlmProvider::factory()->make(['code' => 'milaha/on-prem', 'input_cost' => 2.0, 'output_cost' => 6.0]);

    expect($pricing->ratesFor($known))->toBe(['input' => 1.25, 'output' => 10.0]) // catalog wins over the row
        ->and($pricing->ratesFor($custom))->toBe(['input' => 2.0, 'output' => 6.0]) // unknown model uses the row
        // 45 in + 113 out at $1.25/$10 per 1M ≈ a tenth of a cent, not dollars.
        ->and($pricing->estimate($known, 45, 113))->toBe(45 / 1_000_000 * 1.25 + 113 / 1_000_000 * 10.0)
        ->and($pricing->estimate($known, 45, 113))->toBeLessThan(0.01);
});

test('the model pricing catalog stays within the per-million units guardrail', function () {
    $ceiling = (float) config('maacc.pricing.max_rate_per_million');
    $models = config('maacc.pricing.models');

    expect($models)->not->toBeEmpty();

    foreach ($models as $code => $rates) {
        expect((float) $rates['input'])->toBeLessThanOrEqual($ceiling, "input rate for {$code}")
            ->and((float) $rates['output'])->toBeLessThanOrEqual($ceiling, "output rate for {$code}");
    }
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
        ->and($registry->has('sum'))->toBeTrue()
        ->and($registry->has('vessel_status'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->resolve('echo')->handle(['message' => 'hi']))->toBe(['message' => 'hi'])
        ->and($registry->resolve('echo')->handle([]))->toBe(['message' => ''])
        ->and($registry->resolve('current_time')->handle([]))->toHaveKey('iso');

    expect(fn () => $registry->resolve('missing'))->toThrow(RuntimeException::class);
});

test('the hosted sum tool adds numeric values and ignores the rest', function () {
    $sum = (new HostedToolRegistry)->resolve('sum');

    expect($sum->handle(['numbers' => [10, 2.5, 'skip', 7]]))->toBe(['total' => 19.5])
        ->and($sum->handle([]))->toBe(['total' => 0.0])
        ->and($sum->handle(['numbers' => 'not-an-array']))->toBe(['total' => 0.0]);
});

test('the hosted vessel status tool returns fleet data or an unknown fallback', function () {
    $tool = (new HostedToolRegistry)->resolve('vessel_status');

    expect($tool->handle(['vessel' => 'MV Al-Zubarah']))
        ->toMatchArray(['vessel' => 'MV Al-Zubarah', 'port' => 'Hamad'])
        ->and($tool->handle(['vessel' => 'MV Ghost'])['status'])->toBe('No active voyage on record')
        ->and($tool->handle([])['status'])->toBe('No active voyage on record');
});

test('the AI router unwraps a fenced tool-call envelope', function () {
    Ai::fakeAgent(RuntimeAgent::class, ["```json\n{\"tool\":\"lookup\",\"arguments\":{\"q\":\"x\"}}\n```"]);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'anthropic',
        modelCode: 'claude-3',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('find x')],
        tools: [new LlmToolDefinition('lookup', 'Looks things up', ['q' => 'string'])],
    ));

    expect($completion->isToolCall())->toBeTrue()
        ->and($completion->toolName)->toBe('lookup')
        ->and($completion->toolArguments)->toBe(['q' => 'x']);
});

test('the AI router surfaces a native tool call returned by the provider', function () {
    Ai::fakeAgent(RuntimeAgent::class, [
        new ToolCall('call_1', 'lookup', ['q' => 'x']),
        'Looked it up.',
    ]);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'openai',
        modelCode: 'gpt-5.4',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('find x')],
        tools: [new LlmToolDefinition('lookup', 'Looks things up', ['q' => 'string'])],
    ));

    expect($completion->isToolCall())->toBeTrue()
        ->and($completion->toolName)->toBe('lookup')
        ->and($completion->toolArguments)->toBe(['q' => 'x']);
});

test('the AI router exposes web search as a provider-hosted tool', function () {
    Ai::fakeAgent(RuntimeAgent::class, [
        new ToolCall('call_1', 'webSearch', ['query' => 'latest world cup scores']),
        'Provider searched the web.',
    ]);

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'openai',
        modelCode: 'gpt-5.4',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('find the latest scores')],
        tools: [new LlmToolDefinition('lookup', 'Looks things up', ['q' => 'string'])],
        providerTools: [LlmProviderToolDefinition::webSearch()],
    ));

    Ai::assertAgentWasPrompted(RuntimeAgent::class, function (AgentPrompt $prompt): bool {
        $tools = [...$prompt->agent->tools()];

        expect($tools)->toHaveCount(2)
            ->and($tools[0])->toBeInstanceOf(RuntimeTool::class)
            ->and($tools[1])->toBeInstanceOf(WebSearch::class);

        return true;
    });

    expect($completion->isToolCall())->toBeFalse()
        ->and($completion->text)->toBe('Provider searched the web.');
});

test('the AI router rejects unknown provider-hosted tool types', function () {
    expect(fn () => (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'openai',
        modelCode: 'gpt-5.4',
        systemPrompt: 'You help.',
        messages: [LlmMessage::user('find the latest scores')],
        providerTools: [new LlmProviderToolDefinition('unknown', 'unknown')],
    )))->toThrow(InvalidArgumentException::class, 'Unsupported provider-hosted tool type [unknown].');
});

test('the provider-hosted registry recognizes only hosted web search contracts', function () {
    $registry = new ProviderHostedToolRegistry;
    $webSearch = ToolContract::factory()->make([
        'slug' => 'webSearch',
        'execution_mode' => ExecMode::Hosted,
    ]);
    $snakeWebSearch = ToolContract::factory()->make([
        'slug' => 'web_search',
        'execution_mode' => ExecMode::Hosted,
    ]);
    $clientWebSearch = ToolContract::factory()->make([
        'slug' => 'webSearch',
        'execution_mode' => ExecMode::Client,
    ]);
    $hostedEcho = ToolContract::factory()->make([
        'slug' => 'echo',
        'execution_mode' => ExecMode::Hosted,
    ]);

    expect($registry->has($webSearch))->toBeTrue()
        ->and($registry->definitionFor($webSearch))->toEqual(LlmProviderToolDefinition::webSearch('webSearch'))
        ->and($registry->has($snakeWebSearch))->toBeTrue()
        ->and($registry->definitionFor($snakeWebSearch))->toEqual(LlmProviderToolDefinition::webSearch('web_search'))
        ->and($registry->has($clientWebSearch))->toBeFalse()
        ->and($registry->definitionFor($clientWebSearch))->toBeNull()
        ->and($registry->has($hostedEcho))->toBeFalse()
        ->and($registry->definitionFor($hostedEcho))->toBeNull();
});

test('a runtime tool maps the MAACC schema DSL to native JSON-schema types', function () {
    $tool = new RuntimeTool(new LlmToolDefinition('lookup', '', [
        'name' => 'string',
        'count' => 'integer',
        'ratio' => 'number',
        'active' => 'boolean',
        'tags' => 'array',
        'meta' => 'object',
        'note' => 'string?',
    ]));

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($tool->name())->toBe('lookup')
        ->and((string) $tool->description())->toBe('lookup') // empty description falls back to the name
        ->and(array_keys($schema))->toBe(['name', 'count', 'ratio', 'active', 'tags', 'meta', 'note'])
        ->and((string) (new RuntimeTool(new LlmToolDefinition('x', 'A described tool', [])))->description())->toBe('A described tool')
        ->and((string) $tool->handle(new Request([])))->toBe('');
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
