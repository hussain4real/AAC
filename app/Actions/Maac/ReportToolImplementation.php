<?php

namespace App\Actions\Maac;

use App\Enums\Environment;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Support\Sdk\ToolCompatibility;

/**
 * Reconciles client-side tool handlers reported by an application's SDK against
 * their contracts, upserting per-environment {@see ToolImplementation}
 * records with the computed compatibility status.
 */
class ReportToolImplementation
{
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
        $status = ToolCompatibility::evaluate($contract, $version, $fingerprint);

        $implementation = $contract->implementations()->updateOrCreate(
            ['application_id' => $application->id, 'environment' => $environment->value],
            [
                'status' => $status->value,
                'handler_name' => (string) $report['handler_name'],
                'implemented_version' => $version,
                'language' => $report['language'] ?? null,
                'last_validated_at' => now(),
            ],
        );

        return [
            'tool' => $slug,
            'accepted' => true,
            'status' => $status->value,
            'implemented_version' => $version,
            'last_validated_at' => $implementation->last_validated_at?->toIso8601String(),
        ];
    }
}
