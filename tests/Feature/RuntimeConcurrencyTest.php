<?php

use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Enums\TraceEventType;
use App\Jobs\AdvanceAgentRun;
use App\Jobs\ProcessAgentRun;
use App\Models\AgentRun;
use App\Models\TraceEvent;
use App\Support\Runtime\AgentRunner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

test('the recovery sweep expires abandoned work and redispatches stale claims', function () {
    Queue::fake();
    $expired = AgentRun::factory()->waitingForClient()->create([
        'expires_at' => now()->subMinute(),
        'completed_at' => null,
    ]);
    $stale = AgentRun::factory()->create([
        'status' => RunStatus::Queued,
        'mode' => RunMode::Async,
        'completed_at' => null,
        'expires_at' => now()->addHour(),
        'processing_token' => fake()->uuid(),
        'processing_claimed_at' => now()->subMinutes(10),
    ]);

    expect(Artisan::call('maacc:recover-runs'))->toBe(0);

    expect($expired->fresh()->status)->toBe(RunStatus::Expired)
        ->and($expired->fresh()->reserved_tokens)->toBe(0)
        ->and($stale->fresh()->processing_token)->toBeNull();
    Queue::assertPushed(ProcessAgentRun::class, fn (ProcessAgentRun $job): bool => $job->run->is($stale));
});

test('the recovery sweep advances stale running work and ignores a concurrently released claim', function () {
    Queue::fake();
    $running = AgentRun::factory()->create([
        'status' => RunStatus::Running,
        'mode' => RunMode::Async,
        'completed_at' => null,
        'expires_at' => now()->addHour(),
        'processing_token' => 'running-token',
        'processing_claimed_at' => now()->subMinutes(10),
    ]);
    $raced = AgentRun::factory()->create([
        'status' => RunStatus::Queued,
        'mode' => RunMode::Async,
        'completed_at' => null,
        'expires_at' => now()->addHour(),
        'processing_token' => 'raced-token',
        'processing_claimed_at' => now()->subMinutes(10),
    ]);
    $changed = false;

    AgentRun::retrieved(function (AgentRun $run) use ($raced, &$changed): void {
        if (! $changed && $run->is($raced) && $run->processing_token === 'raced-token') {
            $changed = true;
            AgentRun::query()->whereKey($run->id)->update(['processing_token' => 'new-owner-token']);
        }
    });

    expect(Artisan::call('maacc:recover-runs'))->toBe(0);

    Queue::assertPushed(AdvanceAgentRun::class, fn (AdvanceAgentRun $job): bool => $job->run->is($running));
    Queue::assertNotPushed(ProcessAgentRun::class, fn (ProcessAgentRun $job): bool => $job->run->is($raced));
    expect($raced->fresh()->processing_token)->toBe('new-owner-token');
});

test('runtime jobs declare bounded retry visibility and unique delivery policies', function () {
    $run = AgentRun::factory()->create();
    $process = new ProcessAgentRun($run);
    $advance = new AdvanceAgentRun($run);

    expect($process->tries)->toBe(3)
        ->and($process->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($process->backoff())->toBe([10, 30, 60])
        ->and($process->uniqueId())->toBe('process:'.$run->id)
        ->and($advance->uniqueId())->toBe('advance:'.$run->id);
});

test('runtime worker failure callbacks fail only non-terminal runs', function () {
    $processRun = AgentRun::factory()->create(['status' => RunStatus::Queued, 'reserved_tokens' => 10]);
    $advanceRun = AgentRun::factory()->create(['status' => RunStatus::Running, 'reserved_tokens' => 10]);
    $terminal = AgentRun::factory()->create(['status' => RunStatus::Completed, 'failure_reason' => null]);

    (new ProcessAgentRun($processRun))->failed(new RuntimeException('worker'));
    (new AdvanceAgentRun($advanceRun))->failed(null);
    (new ProcessAgentRun($terminal))->failed(null);

    expect($processRun->fresh()->status)->toBe(RunStatus::Failed)
        ->and($processRun->fresh()->failure_reason)->toBe('worker_failed')
        ->and($advanceRun->fresh()->status)->toBe(RunStatus::Failed)
        ->and($terminal->fresh()->status)->toBe(RunStatus::Completed);
});

test('database constraints reject duplicate per-run trace sequences', function () {
    $run = AgentRun::factory()->create();
    TraceEvent::query()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::RunRequested,
        'sequence' => 0,
        'occurred_at' => now(),
    ]);

    expect(fn () => TraceEvent::query()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::Completed,
        'sequence' => 0,
        'occurred_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('duplicate runtime workers and terminal transitions are idempotent', function () {
    $runner = app(AgentRunner::class);
    $terminal = AgentRun::factory()->create(['status' => RunStatus::Completed]);
    $queued = AgentRun::factory()->create(['status' => RunStatus::Queued]);

    expect($runner->processClaimed($terminal)->status)->toBe(RunStatus::Completed)
        ->and($runner->driveClaimed($queued)->status)->toBe(RunStatus::Queued);

    foreach (['complete' => ['text'], 'fail' => ['code', 'internal detail'], 'expire' => [], 'cancel' => []] as $method => $arguments) {
        $reflection = new ReflectionMethod($runner, $method);
        $result = $reflection->invoke($runner, $terminal, ...$arguments);
        expect($result->status)->toBe(RunStatus::Completed);
    }

    $release = new ReflectionMethod($runner, 'releaseClaim');
    $release->invoke($runner, $terminal);

    expect($terminal->fresh()->status)->toBe(RunStatus::Completed);
});
