<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Support\Runtime\AgentRunner;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drives a queued (asynchronous) run on a worker: selects the model, marks the
 * run running, and advances it to its first boundary (completed, paused for a
 * client-side tool, or failed). The run was already created and audited by the
 * runtime API, so the caller never holds the request open.
 */
class ProcessAgentRun implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public AgentRun $run) {}

    /**
     * Execute the job.
     */
    public function handle(AgentRunner $runner): void
    {
        $runner->process($this->run);
    }
}
