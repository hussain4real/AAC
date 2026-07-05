<?php

use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\ReportToolImplementation;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\User;

/**
 * Seed a team with one client tool that has two contract versions and two
 * timeline events (an initial report, then a schema bump that drifts it).
 *
 * @return array{0: Team, 1: ToolContract}
 */
function seedExportJourney(): array
{
    [$owner, $team] = ownerAndTeam();
    test()->actingAs($owner);

    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'name' => 'Fetch Records',
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

    return [$team, $contract];
}

test('the journey export downloads signed JSON with versions and events', function () {
    [$team] = seedExportJourney();

    $response = $this->get("/{$team->slug}/journey/export");

    $response->assertOk()->assertHeader('x-maacc-journey-checksum');
    expect($response->headers->get('content-type'))->toContain('application/json');

    $body = $response->json();
    expect($body['manifest']['team'])->toBe($team->slug)
        ->and($body['manifest']['checksum'])->toBeString()
        ->and($body['manifest']['event_count'])->toBeGreaterThanOrEqual(2)
        ->and($body['manifest']['version_count'])->toBe(2)
        ->and($body['manifest']['truncated'])->toBeFalse()
        ->and($body['versions'])->toHaveCount(2)
        ->and($body['events'])->not->toBeEmpty()
        ->and($response->headers->get('x-maacc-journey-checksum'))->toBe($body['manifest']['checksum']);
});

test('the journey export downloads the timeline as CSV', function () {
    [$team] = seedExportJourney();

    $response = $this->get("/{$team->slug}/journey/export?format=csv");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->getContent();
    expect($csv)->toContain('occurred_at,tool,application,environment,previous_status,status,reason')
        ->and($csv)->toContain('Fetch Records')
        ->and($csv)->toContain('contract_changed');
});

test('the journey export quotes CSV cells that contain a comma', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'name' => 'Fetch, Records',
    ]));

    app(ReportToolImplementation::class)->handle($application, Environment::Production, [[
        'tool' => $contract->slug,
        'handler_name' => 'RecordsHandler',
        'version' => '1.0.0',
        'schema_fingerprint' => $contract->schemaFingerprint(),
    ]]);

    $csv = $this->get("/{$team->slug}/journey/export?format=csv")->getContent();

    // The tool name contains a comma, so its CSV cell must be wrapped in quotes.
    expect($csv)->toContain('"Fetch, Records"');
});

test('the journey export rejects an unknown format', function () {
    [$team] = seedExportJourney();

    $this->getJson("/{$team->slug}/journey/export?format=xml")->assertStatus(422);
});

test('a non-member cannot export another team journey', function () {
    [$team] = seedExportJourney();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/{$team->slug}/journey/export")
        ->assertForbidden();
});
