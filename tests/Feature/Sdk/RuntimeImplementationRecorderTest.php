<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplementationEventReason;
use App\Enums\ImplStatus;
use App\Enums\SdkLanguage;
use App\Models\Application;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\ToolImplementationEvent;
use App\Support\Sdk\RuntimeImplementationRecorder;

/**
 * A schema-valid client-tool execution in a live run marks the application's
 * implementation as validated, keeping the status honest without an explicit
 * SDK report — additively and without spamming the timeline.
 */
function runtimeValidationTool(): array
{
    $team = Team::factory()->create();
    $application = Application::factory()->for($team)->create();
    $tool = ToolContract::factory()->for($team)->for($application)->create([
        'slug' => 'getRecords',
        'execution_mode' => ExecMode::Client,
    ]);

    return [$tool, $application];
}

function recordRuntimeValidation(ToolContract $tool, Application $application): void
{
    app(RuntimeImplementationRecorder::class)->record($tool, $application, Environment::Production);
}

test('a successful runtime execution records the implementation as validated', function () {
    [$tool, $application] = runtimeValidationTool();

    recordRuntimeValidation($tool, $application);

    $implementation = ToolImplementation::firstWhere('tool_contract_id', $tool->id);

    expect($implementation)->not->toBeNull()
        ->and($implementation->status)->toBe(ImplStatus::Implemented)
        ->and($implementation->handler_name)->toBe('Runtime-validated')
        ->and($implementation->environment)->toBe(Environment::Production)
        ->and($implementation->last_validated_at)->not->toBeNull();

    $event = ToolImplementationEvent::firstWhere('tool_contract_id', $tool->id);
    expect($event->reason)->toBe(ImplementationEventReason::RuntimeValidated)
        ->and($event->reason->label())->toBe('Validated by a live run')
        ->and($event->previous_status)->toBeNull();
});

test('repeated runtime executions do not spam the implementation timeline', function () {
    [$tool, $application] = runtimeValidationTool();

    recordRuntimeValidation($tool, $application);
    recordRuntimeValidation($tool, $application);
    recordRuntimeValidation($tool, $application);

    expect(ToolImplementation::where('tool_contract_id', $tool->id)->count())->toBe(1)
        ->and(ToolImplementationEvent::where('tool_contract_id', $tool->id)->count())->toBe(1);
});

test('a runtime execution recovers a drifted report without clobbering its metadata', function () {
    [$tool, $application] = runtimeValidationTool();

    // An earlier SDK report that drifted out of compatibility, with rich metadata.
    $tool->implementations()->create([
        'application_id' => $application->id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Incompatible->value,
        'handler_name' => 'AbsherHandler',
        'implemented_version' => 'v1',
        'schema_fingerprint' => 'stale-fingerprint',
        'language' => 'php',
        'sdk_version' => '0.2.0',
        'last_validated_at' => now(),
    ]);

    recordRuntimeValidation($tool, $application);

    $implementation = ToolImplementation::firstWhere('tool_contract_id', $tool->id);

    expect($implementation->status)->toBe(ImplStatus::Implemented)
        // The application's reported metadata is preserved, not overwritten.
        ->and($implementation->handler_name)->toBe('AbsherHandler')
        ->and($implementation->language)->toBe(SdkLanguage::Php)
        ->and($implementation->sdk_version)->toBe('0.2.0');

    $event = ToolImplementationEvent::query()
        ->where('tool_contract_id', $tool->id)
        ->where('reason', ImplementationEventReason::RuntimeValidated->value)
        ->first();
    expect($event->previous_status)->toBe(ImplStatus::Incompatible);
});

test('a non-client tool is never recorded from a runtime execution', function () {
    $team = Team::factory()->create();
    $application = Application::factory()->for($team)->create();
    $hosted = ToolContract::factory()->for($team)->for($application)->create([
        'execution_mode' => ExecMode::Hosted,
    ]);

    recordRuntimeValidation($hosted, $application);

    expect(ToolImplementation::where('tool_contract_id', $hosted->id)->exists())->toBeFalse();
});
