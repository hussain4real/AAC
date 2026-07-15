<?php

use App\Models\Application;
use App\Models\DataSource;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Support\Governance\TenantAssociationIntegrityScanner;
use Database\Seeders\MaaccDemoSeeder;
use Illuminate\Support\Facades\Artisan;

test('the tenant integrity scanner passes aligned relationships', function () {
    [, $team] = ownerAndTeam();
    maaccAgent($team);

    $exitCode = Artisan::call('maacc:scan-tenant-integrity', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['read_only'])->toBeTrue()
        ->and($report['finding_count'])->toBe(0)
        ->and($report['findings'])->toBe([]);
});

test('the demo enterprise dataset satisfies tenant association invariants', function () {
    $this->seed(MaaccDemoSeeder::class);

    expect(app(TenantAssociationIntegrityScanner::class)->scan())->toBe([]);
});

test('the tenant integrity scanner reports persisted mismatches without changing data', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $foreignTeam = Team::factory()->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();
    $assignment = ToolAssignment::factory()->forAgent($agent)->create([
        'tool_contract_id' => $foreignTool->id,
    ]);
    $unapprovedProvider = LlmProvider::factory()->for($team)->create();
    $policy = ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'fallback_provider_ids' => [$unapprovedProvider->id],
    ]);

    $exitCode = Artisan::call('maacc:scan-tenant-integrity', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $codes = collect($report['findings'])->pluck('code');

    expect($exitCode)->toBe(1)
        ->and($codes)->toContain('tool_assignment_mismatch', 'routing_provider_mismatch')
        ->and(ToolAssignment::query()->find($assignment->id))->not->toBeNull()
        ->and(ModelRoutingPolicy::query()->find($policy->id))->not->toBeNull();
});

test('the human-readable tenant scan reports both clean and mismatched states', function () {
    $cleanExitCode = Artisan::call('maacc:scan-tenant-integrity');
    $cleanOutput = Artisan::output();

    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $unapprovedProvider = LlmProvider::factory()->for($team)->create();
    ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'fallback_provider_ids' => [$unapprovedProvider->id],
    ]);

    $findingExitCode = Artisan::call('maacc:scan-tenant-integrity');
    $findingOutput = Artisan::output();

    expect($cleanExitCode)->toBe(0)
        ->and($cleanOutput)->toContain('Tenant association integrity scan passed with no findings.')
        ->and($findingExitCode)->toBe(1)
        ->and($findingOutput)->toContain(
            'Tenant association integrity scan found 1 mismatch(es).',
            'routing_provider_mismatch',
        );
});

test('the tenant scanner reports parent and tool implementation tenant drift', function () {
    [, $team] = ownerAndTeam();
    [, $foreignTeam] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $foreignApplication = Application::factory()->for($foreignTeam)->create();
    $source = DataSource::factory()->for($team)->create([
        'application_id' => $foreignApplication->id,
    ]);
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();
    $implementation = ToolImplementation::factory()
        ->for($application)
        ->for($foreignTool, 'toolContract')
        ->create();

    $findings = app(TenantAssociationIntegrityScanner::class)->scan();
    $codes = collect($findings)->pluck('code');

    expect($codes)->toContain('tenant_parent_mismatch', 'tool_implementation_mismatch')
        ->and(collect($findings)->where('id', $source->id))->not->toBeEmpty()
        ->and(collect($findings)->where('id', $implementation->id))->not->toBeEmpty();
});
