<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Support\Runtime\AgentRunner;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Continues a queued (asynchronous) run on a worker after a client-side tool
 * result has been accepted, advancing it from `running` to its next boundary.
 */
class AdvanceAgentRun implements ShouldBeEncrypted, ShouldQueue
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
        $runner->drive($this->run);
    }
}
