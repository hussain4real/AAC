<?php

namespace App\Support\Sdk;

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplementationEventReason;
use App\Models\Application;
use App\Models\ToolContract;

/**
 * Records that a client-side tool executed successfully with a schema-valid
 * result during a live run, marking its implementation for the run's environment
 * as validated. This lets real usage keep the implementation status honest even
 * when an application never calls the SDK's explicit report endpoint.
 *
 * It is additive and idempotent: it upgrades a missing or drifted record to
 * implemented and appends a timeline entry only on a genuine status change (so
 * live traffic never spams the journey), preserves the richer metadata of an
 * explicit SDK report (handler name, language, SDK version), and never downgrades
 * an existing record.
 */
final class RuntimeImplementationRecorder
{
    public function __construct(private readonly ImplementationEventRecorder $events) {}

    /**
     * Record a successful, schema-valid runtime execution of a client-side tool.
     */
    public function record(ToolContract $contract, Application $application, Environment $environment): void
    {
        if ($contract->execution_mode !== ExecMode::Client) {
            return;
        }

        $fingerprint = $contract->schemaFingerprint();
        $status = ToolCompatibility::evaluate($contract, $contract->version, $fingerprint);

        $existing = $contract->implementations()
            ->where('application_id', $application->id)
            ->where('environment', $environment->value)
            ->first();

        // Already implemented against the current contract — just refresh the
        // validation timestamp and append no timeline entry, so a tool exercised
        // on every run does not flood the implementation journey.
        if ($existing !== null
            && $existing->status === $status
            && $existing->schema_fingerprint === $fingerprint) {
            $existing->forceFill(['last_validated_at' => now()])->save();

            return;
        }

        $previousStatus = $existing?->status;
        $handlerName = $existing?->handler_name;

        $implementation = $contract->implementations()->updateOrCreate(
            ['application_id' => $application->id, 'environment' => $environment->value],
            [
                'status' => $status->value,
                'handler_name' => $handlerName ?? 'Runtime-validated',
                'implemented_version' => $contract->version,
                'schema_fingerprint' => $fingerprint,
                'language' => $existing?->language,
                'sdk_version' => $existing?->sdk_version,
                'last_validated_at' => now(),
            ],
        );

        $this->events->record($contract, $implementation, $previousStatus, ImplementationEventReason::RuntimeValidated);
    }
}
