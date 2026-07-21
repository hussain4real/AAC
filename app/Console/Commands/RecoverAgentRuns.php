<?php

namespace App\Console\Commands;

use App\Enums\RunStatus;
use App\Jobs\AdvanceAgentRun;
use App\Jobs\ProcessAgentRun;
use App\Models\AgentRun;
use App\Support\Runtime\AgentRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maacc:recover-runs')]
#[Description('Expire abandoned runs and redispatch stale queued or running work')]
class RecoverAgentRuns extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AgentRunner $runner): int
    {
        $expired = 0;
        AgentRun::query()
            ->whereNotIn('status', array_map(static fn (RunStatus $status): string => $status->value, RunStatus::terminalCases()))
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($runs) use ($runner, &$expired): void {
                foreach ($runs as $run) {
                    $runner->refreshExpiry($run);
                    $expired++;
                }
            });

        $staleBefore = now()->subSeconds((int) config('queue.connections.database.retry_after', 180));
        $stale = AgentRun::query()
            ->whereIn('status', [RunStatus::Queued->value, RunStatus::Running->value])
            ->whereNotNull('processing_claimed_at')
            ->where('processing_claimed_at', '<=', $staleBefore)
            ->get();
        $redispatched = 0;

        foreach ($stale as $run) {
            $released = AgentRun::query()
                ->whereKey($run->id)
                ->where('processing_token', $run->processing_token)
                ->update(['processing_token' => null, 'processing_claimed_at' => null]);

            if ($released !== 1) {
                continue;
            }

            $run->status === RunStatus::Queued
                ? ProcessAgentRun::dispatch($run->fresh())
                : AdvanceAgentRun::dispatch($run->fresh());
            $redispatched++;
        }

        $this->info("Expired {$expired} run(s); redispatched {$redispatched} stale claim(s).");

        return self::SUCCESS;
    }
}
