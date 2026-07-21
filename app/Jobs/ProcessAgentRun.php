<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Support\Runtime\AgentRunner;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Drives a queued (asynchronous) run on a worker: selects the model, marks the
 * run running, and advances it to its first boundary (completed, paused for a
 * client-side tool, or failed). The run was already created and audited by the
 * runtime API, so the caller never holds the request open.
 */
class ProcessAgentRun implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 130;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public AgentRun $run) {}

    /**
     * Execute the job.
     */
    public function handle(AgentRunner $runner): void
    {
        $runner->processClaimed($this->run);
    }

    public function uniqueId(): string
    {
        return 'process:'.$this->run->id;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(?Throwable $exception): void
    {
        AgentRun::query()->whereKey($this->run->id)->whereNotIn('status', ['completed', 'failed', 'expired', 'cancelled'])->update([
            'status' => 'failed',
            'failure_reason' => 'worker_failed',
            'error' => 'The run could not be completed.',
            'reserved_tokens' => 0,
            'processing_token' => null,
            'processing_claimed_at' => null,
            'completed_at' => now(),
        ]);
    }
}
