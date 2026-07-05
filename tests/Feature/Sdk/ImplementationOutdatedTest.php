<?php

use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Sdk\ToolImplementationReconciler;
use Illuminate\Support\Facades\Queue;

/**
 * Build a client-side tool contract with one reported implementation, plus a
 * wildcard webhook endpoint, all in production.
 *
 * @return array{0: Application, 1: ToolContract, 2: ToolImplementation}
 */
function seedClientImplementation(string $contractVersion, string $implementedVersion, ImplStatus $status = ImplStatus::Implemented): array
{
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $tool = ToolContract::factory()->for($team)->for($application)->create([
        'slug' => 'fetch_records',
        'execution_mode' => ExecMode::Client,
        'version' => $contractVersion,
    ]);
    $implementation = $tool->implementations()->create([
        'application_id' => $application->id,
        'environment' => Environment::Production->value,
        'status' => $status->value,
        'handler_name' => 'RecordsHandler',
        'implemented_version' => $implementedVersion,
        'last_validated_at' => now(),
    ]);
    WebhookEndpoint::factory()->for($application)->create([
        'environment' => Environment::Production,
        'events' => ['*'],
    ]);

    return [$application, $tool, $implementation];
}

test('a contract version bump flags a current handler outdated and fires the webhook', function () {
    Queue::fake();
    [, $tool, $implementation] = seedClientImplementation('1.0.0', '1.0.0');

    $tool->update(['version' => '2.0.0']);
    app(ToolImplementationReconciler::class)->reconcile($tool);

    expect($implementation->fresh()->status)->toBe(ImplStatus::Outdated);

    $delivery = WebhookDelivery::where('event', WebhookEventType::ImplementationOutdated)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->agent_run_id)->toBeNull()
        ->and($delivery->payload['data'])->toMatchArray([
            'tool' => 'fetch_records',
            'status' => 'outdated',
            'contract_version' => '2.0.0',
            'implemented_version' => '1.0.0',
        ]);

    Queue::assertPushed(DeliverWebhook::class);
});

test('reconciling with no version change leaves the implementation and fires nothing', function () {
    Queue::fake();
    [, $tool, $implementation] = seedClientImplementation('2.0.0', '2.0.0');

    app(ToolImplementationReconciler::class)->reconcile($tool);

    expect($implementation->fresh()->status)->toBe(ImplStatus::Implemented);
    Queue::assertNotPushed(DeliverWebhook::class);
});

test('reconciling a recovered implementation fires a recovered event, not an outdated one', function () {
    Queue::fake();
    // Previously outdated, but its version now matches the contract again.
    [, $tool, $implementation] = seedClientImplementation('1.0.0', '1.0.0', ImplStatus::Outdated);

    app(ToolImplementationReconciler::class)->reconcile($tool);

    expect($implementation->fresh()->status)->toBe(ImplStatus::Implemented)
        ->and(WebhookDelivery::where('event', WebhookEventType::ImplementationRecovered)->exists())->toBeTrue()
        ->and(WebhookDelivery::where('event', WebhookEventType::ImplementationOutdated)->exists())->toBeFalse();

    Queue::assertPushed(DeliverWebhook::class);
});

test('reconciling a non client-side tool is a no-op', function () {
    Queue::fake();
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $hosted = ToolContract::factory()->for($team)->for($application)->create(['execution_mode' => ExecMode::Hosted]);

    app(ToolImplementationReconciler::class)->reconcile($hosted);

    Queue::assertNotPushed(DeliverWebhook::class);
});

test('updating a client tool contract reconciles its implementations', function () {
    Queue::fake();
    [, $tool, $implementation] = seedClientImplementation('1.0.0', '1.0.0');

    app(UpdateToolContract::class)->handle($tool, ['version' => '2.0.0']);

    expect($implementation->fresh()->status)->toBe(ImplStatus::Outdated);
    Queue::assertPushed(DeliverWebhook::class);
});
