<?php

declare(strict_types=1);

namespace Maacc\Sdk\Resources;

/**
 * A single Server-Sent Event from a run stream: an event name (`run.event` for a
 * trace milestone, `run.state` for the final run snapshot) and its decoded data.
 */
final class RunEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $event,
        public readonly array $data,
    ) {}
}
