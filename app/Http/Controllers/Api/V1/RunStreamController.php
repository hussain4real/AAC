<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentRun;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\RunAuthorizer;
use App\Support\Runtime\RunPayload;
use App\Support\Runtime\StreamConcurrencyLimiter;
use App\Support\Sdk\SdkContext;
use App\Support\Sdk\SdkError;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a run's lifecycle as Server-Sent Events for chat-style or
 * progress-oriented interfaces. It tails the run's persisted trace events —
 * emitting each new one as a `run.event` — and finishes with a `run.state`
 * carrying the same {@see RunPayload} envelope the polling API returns, once the
 * run reaches a boundary (terminal or paused for a client tool). Because it
 * replays the audited trace events, a streamed run produces exactly the same
 * trace, audit, cost, and retention data as a synchronous one.
 */
class RunStreamController extends Controller
{
    /**
     * Open an SSE stream for a run owned by the application.
     */
    public function show(Request $request, RunAuthorizer $authorizer, AgentRunner $runner, StreamConcurrencyLimiter $limiter, string $runId): Response
    {
        $context = SdkContext::fromRequest($request);
        $run = $runner->refreshExpiry($authorizer->resolveRun($context->application, $runId));
        $lease = $limiter->acquire($context->application->id);

        if ($lease === null) {
            return SdkError::response(
                'stream_concurrency_exceeded',
                'This application has reached its concurrent stream limit.',
                429,
            )->withHeaders(['Retry-After' => '2', 'X-MAACC-Backpressure' => 'stream-limit']);
        }

        return response()->eventStream(function () use ($run, $lease): Generator {
            try {
                yield from $this->events($run);
            } finally {
                $lease->release();
            }
        });
    }

    /**
     * Yield the run's trace events as they appear, then a final run state, until
     * the run reaches a boundary or the stream's wall-clock budget elapses.
     *
     * @return Generator<int, StreamedEvent>
     */
    private function events(AgentRun $run): Generator
    {
        $interval = max(1, (int) config('maacc.runtime.stream.poll_interval_ms', 500));
        $maxTicks = max(1, (int) ceil(((float) config('maacc.runtime.stream.max_seconds', 60) * 1000) / $interval));
        $cursor = -1;
        $tick = 0;

        while (true) {
            foreach ($run->traceEvents()->where('sequence', '>', $cursor)->orderBy('sequence')->get() as $event) {
                $cursor = (int) $event->sequence;
                yield new StreamedEvent('run.event', (string) json_encode([
                    'type' => $event->type->value,
                    'message' => $event->message,
                    'sequence' => $event->sequence,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ]));
            }

            $run->refresh()->loadMissing('agent');

            if ($run->status->isTerminal() || $run->isWaitingForClient() || ++$tick >= $maxTicks) {
                yield new StreamedEvent('run.state', (string) json_encode(RunPayload::for($run)));

                return;
            }

            usleep($interval * 1000);
        }
    }
}
