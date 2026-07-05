<?php

namespace App\Actions\Maacc;

use App\Enums\Environment;
use App\Enums\ImplementationEventReason;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Support\Sdk\ImplementationEventRecorder;
use App\Support\Sdk\JourneyAuditRecorder;
use App\Support\Sdk\ToolCompatibility;
use App\Support\Webhooks\ImplementationWebhookEmitter;

/**
 * Reconciles client-side tool handlers reported by an application's SDK against
 * their contracts, upserting per-environment {@see ToolImplementation}
 * records with the computed compatibility status, appending an entry to the
 * implementation timeline, and emitting webhook events so the application's
 * other systems stay informed (including `implementation.recovered` when a
 * previously-drifted handler returns to compatibility).
 */
class ReportToolImplementation
{
    public function __construct(
        private readonly ImplementationWebhookEmitter $webhooks,
        private readonly ImplementationEventRecorder $events,
        private readonly JourneyAuditRecorder $audit,
    ) {}

    /**
     * Process a batch of reported handlers, returning a per-tool result entry
     * for each (whether accepted, and the resolved status or error code).
     *
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<int, array<string, mixed>>
     */
    public function handle(Application $application, Environment $environment, array $reports): array
    {
        return array_map(
            fn (array $report): array => $this->reportOne($application, $environment, $report),
            $reports,
        );
    }

    /**
     * Reconcile a single reported handler.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function reportOne(Application $application, Environment $environment, array $report): array
    {
        $slug = (string) $report['tool'];

        $contract = ToolContract::query()
            ->where('application_id', $application->id)
            ->where('slug', $slug)
            ->first();

        if ($contract === null) {
            return ['tool' => $slug, 'accepted' => false, 'error' => 'tool_not_found'];
        }

        if (! $contract->isClientSide()) {
            return ['tool' => $slug, 'accepted' => false, 'error' => 'not_client_side'];
        }

        if ($contract->status === 'Disabled') {
            return ['tool' => $slug, 'accepted' => false, 'error' => 'tool_disabled'];
        }

        $version = (string) $report['version'];
        $fingerprint = isset($report['schema_fingerprint']) ? (string) $report['schema_fingerprint'] : null;
        $sdkVersion = isset($report['sdk_version']) ? (string) $report['sdk_version'] : null;
        $status = ToolCompatibility::evaluate($contract, $version, $fingerprint);

        $previousStatus = $contract->implementations()
            ->where('application_id', $application->id)
            ->where('environment', $environment->value)
            ->first()?->status;

        $implementation = $contract->implementations()->updateOrCreate(
            ['application_id' => $application->id, 'environment' => $environment->value],
            [
                'status' => $status->value,
                'handler_name' => (string) $report['handler_name'],
                'implemented_version' => $version,
                'schema_fingerprint' => $fingerprint,
                'language' => $report['language'] ?? null,
                'sdk_version' => $sdkVersion,
                'last_validated_at' => now(),
            ],
        );

        $this->events->record($contract, $implementation, $previousStatus, ImplementationEventReason::Reported);

        if ($this->events->isRecovery($previousStatus, $status)) {
            $this->audit->implementationTransition($contract, $implementation, $previousStatus, 'tool_implementation.recovered');
            $this->webhooks->emitRecovered($application, $environment, $contract, $implementation);
        }

        $this->webhooks->emitReported($application, $environment, $contract, $implementation);

        return [
            'tool' => $slug,
            'accepted' => true,
            'status' => $status->value,
            'implemented_version' => $version,
            'sdk_version' => $sdkVersion,
            'last_validated_at' => $implementation->last_validated_at?->toIso8601String(),
        ];
    }
}
