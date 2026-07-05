<?php

namespace App\Enums;

/**
 * The events MAACC delivers to a registered webhook endpoint. Most mirror the
 * externally-observable run transitions (the run starts, pauses for a
 * client-side tool, completes, fails, expires, or is cancelled); the
 * implementation event fires when an application reports a client-side tool
 * handler, so its SDK is kept informed of each tool's implementation status.
 */
enum WebhookEventType: string
{
    case RunRunning = 'run.running';
    case RunToolRequested = 'run.tool_requested';
    case RunRequiresApproval = 'run.requires_approval';
    case RunCompleted = 'run.completed';
    case RunFailed = 'run.failed';
    case RunExpired = 'run.expired';
    case RunCancelled = 'run.cancelled';
    case ImplementationReported = 'implementation.reported';
    case ImplementationOutdated = 'implementation.outdated';
    case ImplementationRecovered = 'implementation.recovered';

    /**
     * Get the human-readable label for the event type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RunRunning => 'Run started',
            self::RunToolRequested => 'Tool requested',
            self::RunRequiresApproval => 'Run requires approval',
            self::RunCompleted => 'Run completed',
            self::RunFailed => 'Run failed',
            self::RunExpired => 'Run expired',
            self::RunCancelled => 'Run cancelled',
            self::ImplementationReported => 'Tool implementation reported',
            self::ImplementationOutdated => 'Tool implementation outdated',
            self::ImplementationRecovered => 'Tool implementation recovered',
        };
    }

    /**
     * Get all event types as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Get all event type values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
