<?php

use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\ReportToolImplementation;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\AuditEvent;

test('minting a new contract version records a versioned audit event', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['input_schema' => ['query' => 'string', 'limit' => 'number']]);

    $audit = AuditEvent::query()->where('action', 'tool_contract.versioned')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->team_id)->toBe($team->id)
        ->and($audit->actor_label)->toBe($owner->name)
        ->and($audit->metadata['from'])->toBe('1.0.0')
        ->and($audit->metadata['to'])->toBe('1.0.1')
        ->and($audit->metadata['sequence'])->toBe(2);
});

test('a cosmetic edit records no versioned audit event', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['description' => 'Just a copy tweak']);

    expect(AuditEvent::query()->where('action', 'tool_contract.versioned')->exists())->toBeFalse();
});

test('a contract change that drifts a handler records an outdated audit event', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ]));
    app(ReportToolImplementation::class)->handle($application, Environment::Production, [[
        'tool' => $contract->slug,
        'handler_name' => 'RecordsHandler',
        'version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
    ]]);

    app(UpdateToolContract::class)->handle($contract, ['input_schema' => ['query' => 'string', 'limit' => 'number']]);

    $audit = AuditEvent::query()->where('action', 'tool_implementation.outdated')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata['from'])->toBe('implemented')
        ->and($audit->metadata['to'])->toBe('incompatible')
        ->and($audit->environment)->toBe(Environment::Production);
});

test('a re-report that restores a handler records a recovered audit event', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'version' => '2.0.0',
    ]));

    app(ReportToolImplementation::class)->handle($application, Environment::Production, [[
        'tool' => $contract->slug, 'handler_name' => 'RecordsHandler', 'version' => '1.0.0',
    ]]);
    app(ReportToolImplementation::class)->handle($application, Environment::Production, [[
        'tool' => $contract->slug, 'handler_name' => 'RecordsHandler', 'version' => '2.0.0',
    ]]);

    expect(AuditEvent::query()->where('action', 'tool_implementation.recovered')->exists())->toBeTrue();
});
