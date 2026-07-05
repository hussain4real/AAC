<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplementationEventReason;
use App\Enums\ImplStatus;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\ToolImplementationEvent;
use Illuminate\Support\Facades\DB;

/**
 * Create one client-side contract with a reported implementation under the
 * given team/application.
 *
 * @return array{0: ToolContract, 1: Application, 2: ToolImplementation}
 */
function seedContractWithImplementation(): array
{
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = ToolContract::factory()->for($team)->for($application)->create(['execution_mode' => ExecMode::Client]);
    $implementation = $contract->implementations()->create([
        'application_id' => $application->id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Implemented->value,
        'handler_name' => 'RecordsHandler',
        'implemented_version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
        'last_validated_at' => now(),
    ]);

    return [$contract, $application, $implementation];
}

test('a tool contract version snapshot persists and casts its functional config', function () {
    [$owner] = ownerAndTeam();
    $contract = ToolContract::factory()->create();

    $version = $contract->versions()->create([
        'sequence' => 1,
        'version' => '1.2.0',
        'execution_mode' => ExecMode::Client->value,
        'schema_fingerprint' => $contract->schemaFingerprint(),
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
        'config' => ['timeout_seconds' => 15, 'requires_approval' => false],
        'changed_by' => $owner->id,
        'actor_label' => $owner->name,
        'notes' => 'initial',
    ]);

    $fresh = $version->fresh();

    expect($fresh->execution_mode)->toBe(ExecMode::Client)
        ->and($fresh->sequence)->toBe(1)
        ->and($fresh->input_schema)->toBe(['query' => 'string'])
        ->and($fresh->config)->toBe(['timeout_seconds' => 15, 'requires_approval' => false])
        ->and($fresh->toolContract->is($contract))->toBeTrue()
        ->and($fresh->changedBy->is($owner))->toBeTrue();
});

test('the contract version config is encrypted at rest', function () {
    $contract = ToolContract::factory()->create();

    $version = $contract->versions()->create([
        'sequence' => 1,
        'version' => '1.0.0',
        'execution_mode' => ExecMode::Client->value,
        'schema_fingerprint' => 'fingerprint',
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
        'config' => ['secret_endpoint' => 'https://internal.example.com'],
    ]);

    $raw = (string) DB::table('tool_contract_versions')->where('id', $version->id)->value('config');

    expect($raw)->not->toContain('secret_endpoint')
        ->and($raw)->not->toContain('internal.example.com');
});

test('a tool contract exposes its versions and implementation events', function () {
    [$contract, $application] = seedContractWithImplementation();

    $contract->versions()->create([
        'sequence' => 1,
        'version' => '1.0.0',
        'execution_mode' => ExecMode::Client->value,
        'schema_fingerprint' => 'a',
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ]);
    $contract->versions()->create([
        'sequence' => 2,
        'version' => '1.0.1',
        'execution_mode' => ExecMode::Client->value,
        'schema_fingerprint' => 'b',
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ]);
    ToolImplementationEvent::factory()->create([
        'tool_contract_id' => $contract->id,
        'application_id' => $application->id,
    ]);

    expect($contract->versions)->toHaveCount(2)
        ->and($contract->implementationEvents)->toHaveCount(1);
});

test('a tool implementation event casts statuses and resolves its relations', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $contract = ToolContract::factory()->for($team)->for($application)->create(['execution_mode' => ExecMode::Client]);
    $implementation = $contract->implementations()->create([
        'application_id' => $application->id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Implemented->value,
        'implemented_version' => '1.0.0',
        'last_validated_at' => now(),
    ]);
    $version = $contract->versions()->create([
        'sequence' => 1,
        'version' => '1.0.1',
        'execution_mode' => ExecMode::Client->value,
        'schema_fingerprint' => 'a',
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ]);

    $event = ToolImplementationEvent::create([
        'tool_contract_id' => $contract->id,
        'application_id' => $application->id,
        'tool_implementation_id' => $implementation->id,
        'tool_contract_version_id' => $version->id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Implemented->value,
        'previous_status' => ImplStatus::Outdated->value,
        'reason' => ImplementationEventReason::Reported->value,
        'reported_version' => '1.0.0',
        'schema_fingerprint' => 'abc',
        'contract_version' => '1.0.1',
        'actor_user_id' => $owner->id,
        'actor_label' => $owner->name,
    ]);

    $fresh = $event->fresh();

    expect($fresh->status)->toBe(ImplStatus::Implemented)
        ->and($fresh->previous_status)->toBe(ImplStatus::Outdated)
        ->and($fresh->reason)->toBe(ImplementationEventReason::Reported)
        ->and($fresh->environment)->toBe(Environment::Production)
        ->and($fresh->toolContract->is($contract))->toBeTrue()
        ->and($fresh->application->is($application))->toBeTrue()
        ->and($fresh->toolImplementation->is($implementation))->toBeTrue()
        ->and($fresh->contractVersion->is($version))->toBeTrue()
        ->and($fresh->actor->is($owner))->toBeTrue();
});

test('a tool implementation exposes its events', function () {
    [$contract, $application, $implementation] = seedContractWithImplementation();

    ToolImplementationEvent::factory()->create([
        'tool_contract_id' => $contract->id,
        'application_id' => $application->id,
        'tool_implementation_id' => $implementation->id,
    ]);

    expect($implementation->events)->toHaveCount(1);
});

test('implementation event reasons expose labels', function () {
    expect(ImplementationEventReason::Reported->label())->toBe('Reported by SDK')
        ->and(ImplementationEventReason::ContractChanged->label())->toBe('Contract changed');
});
