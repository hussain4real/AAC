<?php

use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\ReportToolImplementation;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Team;
use App\Models\ToolContract;
use App\Support\Sdk\VersionJourney;

/**
 * Seed a team with one client tool (created through the action so it carries a
 * v1 snapshot), a reported handler, then a schema bump that drifts the handler
 * to incompatible — yielding two contract versions and two timeline events.
 *
 * @return array{0: Team, 1: Application, 2: ToolContract}
 */
function seedJourney(): array
{
    [$owner, $team] = ownerAndTeam();
    test()->actingAs($owner);

    $application = Application::factory()->for($team)->create([
        'environment' => Environment::Production,
        'name' => 'Node Test Client',
    ]);

    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'name' => 'Fetch Port Records',
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
        'version' => '1.0.0',
    ]));

    app(ReportToolImplementation::class)->handle($application, Environment::Production, [[
        'tool' => $contract->slug,
        'handler_name' => 'PortRecordsHandler',
        'version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
        'language' => 'typescript',
        'sdk_version' => '0.2.0',
    ]]);

    app(UpdateToolContract::class)->handle($contract, ['input_schema' => ['query' => 'string', 'limit' => 'number']]);

    return [$team, $application, $contract->fresh()];
}

test('the tool report assembles versions, current implementations, and the event timeline', function () {
    [, $application, $contract] = seedJourney();

    $report = app(VersionJourney::class)->toolReport($contract);

    expect($report['slug'])->toBe($contract->slug)
        ->and($report['name'])->toBe('Fetch Port Records')
        ->and($report['current_version'])->toBe('1.0.1')
        ->and($report['owner'])->toBe($application->slug)
        ->and($report['application'])->toBe('Node Test Client')
        ->and($report['versions'])->toHaveCount(2)
        ->and($report['versions'][0]['version'])->toBe('1.0.1')
        ->and($report['versions'][0]['is_current'])->toBeTrue()
        ->and($report['versions'][0]['changed_by'])->not->toBeNull()
        ->and($report['versions'][1]['version'])->toBe('1.0.0')
        ->and($report['versions'][1]['is_current'])->toBeFalse()
        ->and($report['implementations'])->toHaveCount(1)
        ->and($report['implementations'][0]['status'])->toBe('incompatible')
        ->and($report['implementations'][0]['application'])->toBe('Node Test Client')
        ->and($report['drift_count'])->toBe(1)
        ->and($report['events_truncated'])->toBeFalse();

    $reasons = array_column($report['events'], 'reason');
    expect($reasons)->toContain('reported')->toContain('contract_changed');
});

test('the owner label falls back to platform for a global tool report', function () {
    [, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->global()->create();

    $report = app(VersionJourney::class)->toolReport($tool);

    expect($report['owner'])->toBe('Platform')
        ->and($report['application'])->toBeNull();
});

test('the team report includes every client tool and a per-application rollup', function () {
    [$team, $application, $contract] = seedJourney();

    $report = app(VersionJourney::class)->teamReport($team);

    expect($report['tools'])->toHaveCount(1)
        ->and($report['tools'][0]['slug'])->toBe($contract->slug)
        ->and($report['applications'])->toHaveCount(1)
        ->and($report['applications'][0]['slug'])->toBe($application->slug)
        ->and($report['applications'][0]['tools'][0]['tool'])->toBe('Fetch Port Records')
        ->and($report['applications'][0]['tools'][0]['status'])->toBe('incompatible')
        ->and($report['applications'][0]['tools'][0]['contract_version'])->toBe('1.0.1')
        ->and($report['applications'][0]['drift_count'])->toBe(1)
        ->and($report['truncated'])->toBeFalse();
});

test('the application report lists tool handlers and their drift', function () {
    [, $application] = seedJourney();

    $report = app(VersionJourney::class)->applicationReport($application);

    expect($report['name'])->toBe('Node Test Client')
        ->and($report['tools'])->toHaveCount(1)
        ->and($report['tools'][0]['tool'])->toBe('Fetch Port Records')
        ->and($report['drift_count'])->toBe(1)
        ->and($report['events'])->not->toBeEmpty();
});

test('a small event limit truncates the timeline and flags it', function () {
    [$team, , $contract] = seedJourney();

    $limited = app(VersionJourney::class)->toolReport($contract, 1);
    expect($limited['events'])->toHaveCount(1)
        ->and($limited['events_truncated'])->toBeTrue();

    $full = app(VersionJourney::class)->toolReport($contract, null);
    expect(count($full['events']))->toBeGreaterThan(1)
        ->and($full['events_truncated'])->toBeFalse();

    expect(app(VersionJourney::class)->teamReport($team, 1)['truncated'])->toBeTrue();
});
