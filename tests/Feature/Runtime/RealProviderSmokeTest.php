<?php

use App\Enums\LlmFinishReason;
use App\Enums\RunStatus;
use App\Enums\TraceEventType;
use App\Models\Agent;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\LlmMessage;
use App\Support\Runtime\LlmRequest;

/*
|--------------------------------------------------------------------------
| Live real-provider smoke (guarded)
|--------------------------------------------------------------------------
|
| These tests exercise the production App\Support\Runtime\AiLlmRouter against a
| REAL OpenAI provider — there is no Ai::fake here. They are skipped unless
| MAACC_OPENAI_SMOKE_KEY is set, so `composer ci:check` and the coverage gate are
| unaffected (AiLlmRouter's own lines are fully covered by RuntimeSupportTest via
| the fake gateway; this file adds live confidence, not coverage).
|
| Run, once a key is exported:
|   MAACC_OPENAI_SMOKE_KEY=sk-... php artisan test tests/Feature/Runtime/RealProviderSmokeTest.php
|
*/

/**
 * Skip unless a real key is configured; return the [key, model] to use.
 *
 * @return array{0: string, 1: string}
 */
function openAiSmoke(): array
{
    $key = env('MAACC_OPENAI_SMOKE_KEY');

    if (! is_string($key) || $key === '') {
        test()->markTestSkipped('Set MAACC_OPENAI_SMOKE_KEY to run the live OpenAI provider smoke.');
    }

    $model = env('MAACC_OPENAI_SMOKE_MODEL');

    return [$key, is_string($model) && $model !== '' ? $model : 'gpt-5.4'];
}

test('probe: the production AI router gets a real completion from the model', function () {
    [$key, $model] = openAiSmoke();

    $completion = (new AiLlmRouter)->complete(new LlmRequest(
        providerDriver: 'openai',
        modelCode: $model,
        systemPrompt: 'You are a connectivity probe. Reply with a single short word.',
        messages: [LlmMessage::user('Reply with the word: ready')],
        timeoutSeconds: 60,
        apiKey: $key,
    ));

    fwrite(STDERR, "\n[probe] model={$model} text=".trim((string) $completion->text).
        " tokens_in={$completion->usage->tokensIn} tokens_out={$completion->usage->tokensOut}\n");

    expect($completion->finishReason)->toBe(LlmFinishReason::Stop)
        ->and(trim((string) $completion->text))->not->toBe('')
        ->and($completion->usage->tokensIn)->toBeGreaterThan(0)
        ->and($completion->usage->tokensOut)->toBeGreaterThan(0);
});

test('a published no-tools agent completes a real run using the vault key', function () {
    [$key, $model] = openAiSmoke();
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:openai-smoke', [
        '--team' => $team->slug,
        '--key' => $key,
        '--model' => $model,
    ])->assertSuccessful();

    $agent = Agent::query()->where('agent_slug', 'gpt54-smoke')->with(['tools', 'llmProvider', 'project.application'])->firstOrFail();

    $run = app(AgentRunner::class)->start(
        $agent,
        $agent->project->application,
        $agent->project->application->environment,
        'In one short sentence, what is the capital of Qatar?',
        'smoke',
    );

    fwrite(STDERR, "\n[no-tools] status={$run->status->value} output=".substr((string) $run->output, 0, 200).
        " tokens_in={$run->tokens_in} tokens_out={$run->tokens_out} cost={$run->cost}\n");

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->not->toBe('')
        ->and($run->tokens_in)->toBeGreaterThan(0)
        ->and($run->tokens_out)->toBeGreaterThan(0)
        ->and($run->cost)->toBeGreaterThan(0);
});

test('a published agent invokes the MAACC-hosted vessel_status tool on a real run', function () {
    [$key, $model] = openAiSmoke();
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:openai-smoke', [
        '--team' => $team->slug,
        '--key' => $key,
        '--model' => $model,
    ])->assertSuccessful();

    $agent = Agent::query()->where('agent_slug', 'gpt54-tool')->with(['tools', 'llmProvider', 'project.application'])->firstOrFail();

    $run = app(AgentRunner::class)->start(
        $agent,
        $agent->project->application,
        $agent->project->application->environment,
        'What is the current status, port, and ETA of the vessel MV Al-Zubarah?',
        'smoke',
    );

    $types = $run->traceEvents()->orderBy('sequence')->pluck('type')->map->value->all();

    fwrite(STDERR, "\n[hosted-tool] status={$run->status->value} output=".substr((string) $run->output, 0, 240).
        ' trace='.implode(',', $types)."\n");

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($types)->toContain(TraceEventType::ToolRequired->value)
        ->and($types)->toContain(TraceEventType::ToolResultReceived->value)
        ->and($run->toolCalls()->where('tool_name', 'vessel_status')->exists())->toBeTrue();
});
