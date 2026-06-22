<?php

declare(strict_types=1);

namespace Maac\Sdk\Exceptions;

use Maac\Sdk\Resources\Run;

/**
 * Thrown by the auto-resume run loop when a run cannot be driven to a terminal
 * state — either it exceeded the loop's iteration guard while still waiting, or
 * it reached a non-completed terminal status (failed, expired, cancelled). The
 * offending {@see Run} is attached for inspection.
 */
class RunNotResolvedException extends MaacException
{
    public function __construct(public readonly Run $run, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The run did not finish within the loop's iteration budget.
     */
    public static function exhausted(Run $run, int $iterations): self
    {
        return new self($run, "The run [{$run->runId}] did not finish within {$iterations} tool iterations (status: {$run->status}).");
    }

    /**
     * The run reached a terminal status other than completed.
     */
    public static function terminal(Run $run): self
    {
        return new self($run, "The run [{$run->runId}] ended with status [{$run->status}]: ".($run->error ?? 'no detail provided').'.');
    }
}
