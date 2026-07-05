<?php

use App\Actions\Maacc\ReportToolImplementation;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplementationEventReason;
use App\Enums\ImplStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Sdk\ToolImplementationReconciler;
use Illuminate\Support\Facades\Queue;

/**
 * Build a client-side contract (with a wildcard webhook endpoint) under a fresh
 * team/application in production.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: Application, 1: ToolContract}
 */
function seedTimelineContract(string $version = '1.0.0', array $attributes = []): array
{
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = ToolContract::factory()->for($team)->for($application)->create(array_merge([
        'slug' => 'fetch_records',
        'execution_mode' => ExecMode::Client,
        'version' => $version,
    ], $attributes));
    WebhookEndpoint::factory()->for($application)->create([
        'environment' => Environment::Production,
        'events' => ['*'],
    ]);

    return [$application, $contract];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<int, array<string, mixed>>
 */
function timelineReportPayload(array $overrides = []): array
{
    return [array_merge([
        'tool' => 'fetch_records',
        'handler_name' => 'RecordsHandler',
        'version' => '1.0.0',
    ], $overrides)];
}

/**
 * @param  array<int, array<string, mixed>>  $reports
 * @return array<int, array<string, mixed>>
 */
function runTimelineReport(Application $application, array $reports): array
{
    return app(ReportToolImplementation::class)->handle($application, Environment::Production, $reports);
}

test('a report persists the schema fingerprint and records a timeline event', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('1.0.0');

    runTimelineReport($application, timelineReportPayload([
        'version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
        'language' => 'typescript',
        'sdk_version' => '0.2.0',
    ]));

    $implementation = $contract->implementations()->first();
    expect($implementation->schema_fingerprint)->toBe($contract->schemaFingerprint())
        ->and($implementation->status)->toBe(ImplStatus::Implemented);

    $event = $contract->implementationEvents()->first();
    expect($event)->not->toBeNull()
        ->and($event->reason)->toBe(ImplementationEventReason::Reported)
        ->and($event->status)->toBe(ImplStatus::Implemented)
        ->and($event->previous_status)->toBeNull()
        ->and($event->reported_version)->toBe('1.0.0')
        ->and($event->schema_fingerprint)->toBe($contract->schemaFingerprint())
        ->and($event->contract_version)->toBe('1.0.0')
        ->and($event->tool_implementation_id)->toBe($implementation->id);
});

test('every report appends a timeline event, even with no status change', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('1.0.0');

    runTimelineReport($application, timelineReportPayload(['version' => '1.0.0']));
    runTimelineReport($application, timelineReportPayload(['version' => '1.0.0']));

    expect($contract->implementationEvents()->count())->toBe(2);
});

test('reporting an older handler then a current one fires a recovered event', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('2.0.0');

    // First report lags the contract → outdated; the second catches up → recovered.
    runTimelineReport($application, timelineReportPayload(['version' => '1.0.0']));
    runTimelineReport($application, timelineReportPayload(['version' => '2.0.0']));

    expect($contract->implementations()->first()->status)->toBe(ImplStatus::Implemented);

    $events = $contract->implementationEvents()->get();
    expect($events)->toHaveCount(2)
        ->and($events->firstWhere('status', ImplStatus::Outdated)->previous_status)->toBeNull()
        ->and($events->firstWhere('status', ImplStatus::Implemented)->previous_status)->toBe(ImplStatus::Outdated);

    expect(WebhookDelivery::where('event', WebhookEventType::ImplementationRecovered)->exists())->toBeTrue();
    Queue::assertPushed(DeliverWebhook::class);
});

test('a schema change marks a fingerprinted handler incompatible and records the transition', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('1.0.0', [
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ]);
    runTimelineReport($application, timelineReportPayload([
        'version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
    ]));

    // Change the schema shape: mints a new version with a new fingerprint.
    app(UpdateToolContract::class)->handle($contract, [
        'input_schema' => ['query' => 'string', 'limit' => 'number'],
    ]);

    expect($contract->implementations()->first()->status)->toBe(ImplStatus::Incompatible);

    $event = $contract->implementationEvents()
        ->where('reason', ImplementationEventReason::ContractChanged->value)
        ->first();
    expect($event)->not->toBeNull()
        ->and($event->previous_status)->toBe(ImplStatus::Implemented)
        ->and($event->status)->toBe(ImplStatus::Incompatible)
        ->and($event->tool_contract_version_id)->not->toBeNull();

    expect(WebhookDelivery::where('event', WebhookEventType::ImplementationOutdated)->exists())->toBeTrue();
});

test('a handler reported without a fingerprint drifts to outdated, not incompatible', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('1.0.0');
    runTimelineReport($application, timelineReportPayload(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, [
        'input_schema' => ['query' => 'string', 'limit' => 'number'],
    ]);

    expect($contract->implementations()->first()->status)->toBe(ImplStatus::Outdated);
});

test('a reconcile that restores compatibility fires a recovered event and records it', function () {
    Queue::fake();
    [$application, $contract] = seedTimelineContract('1.0.0');
    $contract->implementations()->create([
        'application_id' => $application->id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Outdated->value,
        'implemented_version' => '1.0.0',
        'last_validated_at' => now(),
    ]);

    app(ToolImplementationReconciler::class)->reconcile($contract);

    expect($contract->implementations()->first()->status)->toBe(ImplStatus::Implemented);

    $event = $contract->implementationEvents()->first();
    expect($event->previous_status)->toBe(ImplStatus::Outdated)
        ->and($event->status)->toBe(ImplStatus::Implemented)
        ->and($event->reason)->toBe(ImplementationEventReason::ContractChanged);

    expect(WebhookDelivery::where('event', WebhookEventType::ImplementationRecovered)->exists())->toBeTrue();
});
