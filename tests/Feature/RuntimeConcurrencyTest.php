<?php

use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Enums\TraceEventType;
use App\Jobs\AdvanceAgentRun;
use App\Jobs\ProcessAgentRun;
use App\Models\AgentRun;
use App\Models\TraceEvent;
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
