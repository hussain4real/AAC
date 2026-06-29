<?php

use App\Enums\DataSourceStatus;
use App\Enums\ExecMode;
use App\Enums\VaultSecretKind;
use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalManager;
use App\Support\Secrets\Contracts\SecretVault;
use Inertia\Testing\AssertableInertia as Assert;

test('the data sources console page renders with the connection allowlist', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('data-sources', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('maac/data-sources')
            ->where('connections', ['maac_reporting']));
});

test('a platform admin registers an active internal data source', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Port Reporting Replica',
            'description' => 'Read-only reporting replica.',
            'connection_type' => 'read_replica',
            'connection' => 'maac_reporting',
            'sensitivity' => 'internal',
            'environments' => ['production', 'staging'],
            'allowed_relations' => ['reporting_port_calls'],
        ])
        ->assertRedirect();

    $source = DataSource::firstWhere('name', 'Port Reporting Replica');

    expect($source)->not->toBeNull()
        ->and($source->team_id)->toBe($team->id)
        ->and($source->status)->toBe(DataSourceStatus::Active)
        ->and($source->requires_approval)->toBeFalse()
        ->and($source->driver)->toBe('sqlite')
        ->and($source->allowedRelations())->toBe(['reporting_port_calls'])
        ->and($source->creator->is($owner))->toBeTrue()
        ->and(ApprovalRequest::where('subject_id', $source->id)->exists())->toBeFalse();
});

test('a confidential data source is gated behind access approval', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Finance Replica',
            'connection_type' => 'reporting_schema',
            'connection' => 'maac_reporting',
            'sensitivity' => 'confidential',
            'environments' => ['production'],
            'allowed_relations' => ['reporting_finance'],
        ])
        ->assertRedirect();

    $source = DataSource::firstWhere('name', 'Finance Replica');

    expect($source->status)->toBe(DataSourceStatus::Draft)
        ->and($source->requires_approval)->toBeTrue()
        ->and(ApprovalRequest::where('subject_id', $source->id)->where('type', 'data_source_access')->exists())->toBeTrue();
});

test('an internal data source flagged for approval is also gated', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Flagged Replica',
            'connection_type' => 'read_replica',
            'connection' => 'maac_reporting',
            'sensitivity' => 'internal',
            'requires_approval' => true,
            'environments' => ['production'],
            'allowed_relations' => ['reporting_port_calls'],
        ])
        ->assertRedirect();

    expect(DataSource::firstWhere('name', 'Flagged Replica')->status)->toBe(DataSourceStatus::Draft);
});

test('data source registration validates its required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => '',
            'environments' => [],
            'allowed_relations' => [],
        ])
        ->assertSessionHasErrors(['name', 'connection_type', 'connection', 'sensitivity', 'environments', 'allowed_relations']);
});

test('a data source cannot reference a connection outside the approved allowlist', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Sneaky Source',
            'connection_type' => 'read_replica',
            // The app's own operational connection is NOT on the allowlist.
            'connection' => 'sqlite',
            'sensitivity' => 'internal',
            'environments' => ['production'],
            'allowed_relations' => ['users'],
        ])
        ->assertSessionHasErrors(['connection']);
});

test('a plain member cannot register a data source', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'connection_type' => 'read_replica',
            'connection' => 'maac_reporting',
            'sensitivity' => 'internal',
            'environments' => ['production'],
            'allowed_relations' => ['reporting_port_calls'],
        ])
        ->assertForbidden();
});

test('a data source can be updated, re-pointed, and toggled', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create(['connection' => 'sqlite', 'driver' => null]);

    $this->actingAs($owner)
        ->put(route('data-sources.update', ['current_team' => $team->slug, 'dataSource' => $source->slug]), [
            'name' => 'Renamed Replica',
            'connection' => 'maac_reporting',
            'status' => 'disabled',
        ])
        ->assertRedirect();

    $fresh = $source->fresh();
    expect($fresh->name)->toBe('Renamed Replica')
        ->and($fresh->status)->toBe(DataSourceStatus::Disabled)
        ->and($fresh->driver)->toBe('sqlite');
});

test('a data source can be marked refreshed', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create(['data_refreshed_at' => now()->subDays(3)]);

    $this->actingAs($owner)
        ->post(route('data-sources.refresh', ['current_team' => $team->slug, 'dataSource' => $source->slug]))
        ->assertRedirect();

    expect($source->fresh()->data_refreshed_at->isToday())->toBeTrue();
});

test('a data source can be deleted', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->delete(route('data-sources.destroy', ['current_team' => $team->slug, 'dataSource' => $source->slug]))
        ->assertRedirect();

    expect($source->fresh()->trashed())->toBeTrue();
});

test('approving a data source access request activates the source', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->draft()->requiresApproval()->create();
    $request = app(ApprovalManager::class)->requestDataSourceAccess($source, $owner);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($source->fresh()->status)->toBe(DataSourceStatus::Active)
        ->and($request->fresh()->status->value)->toBe('approved');
});

test('the shared maac prop exposes data sources without any secret material', function () {
    [$owner, $team] = ownerAndTeam();
    DataSource::factory()->for($team)->create(['name' => 'Reporting Replica', 'connection' => 'maac_reporting']);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maac.dataSources', 1)
            ->where('maac.dataSources.0.name', 'Reporting Replica')
            ->where('maac.dataSources.0.credentialManaged', false)
            // The connection name, connection string, and credential never leave the server.
            ->missing('maac.dataSources.0.connection')
            ->missing('maac.dataSources.0.vault_secret_id'));
});

test('a db-mode tool contract maps to a data source with a query config', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'portMetrics',
            'scope' => 'global',
            'execution_mode' => 'db',
            'sensitivity' => 'internal',
            'timeout_seconds' => 10,
            'max_payload_kb' => 256,
            'input_schema' => ['region' => 'string?'],
            'output_schema' => ['rows' => 'array', 'row_count' => 'integer'],
            'data_source_id' => $source->id,
            'db_config' => [
                'query' => 'select port, calls from reporting_port_calls where region = :region',
                'bindings' => ['region'],
                'columns' => ['port', 'calls'],
                'row_limit' => 25,
                'max_age_minutes' => 1440,
            ],
        ])
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'portMetrics');

    expect($tool->execution_mode)->toBe(ExecMode::Db)
        ->and($tool->data_source_id)->toBe($source->id)
        ->and($tool->dbConfig()['query'])->toContain('reporting_port_calls')
        ->and($tool->dbConfig()['bindings'])->toBe(['region'])
        ->and($tool->dbConfig()['columns'])->toBe(['port', 'calls'])
        ->and($tool->dbConfig()['row_limit'])->toBe(25)
        ->and($tool->dbConfig()['max_age_minutes'])->toBe(1440)
        ->and($tool->status)->toBe('Active');
});

test('a sensitive db tool requiring approval starts as a draft', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'confidentialMetrics',
            'scope' => 'global',
            'execution_mode' => 'db',
            'sensitivity' => 'confidential',
            'requires_approval' => true,
            'timeout_seconds' => 10,
            'max_payload_kb' => 256,
            'input_schema' => ['region' => 'string'],
            'output_schema' => ['rows' => 'array', 'row_count' => 'integer'],
            'data_source_id' => $source->id,
            'db_config' => [
                'query' => 'select port from reporting_port_calls where region = :region',
                'bindings' => ['region'],
            ],
        ])
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'confidentialMetrics');

    expect($tool->status)->toBe('Draft')
        ->and(ApprovalRequest::where('subject_id', $tool->id)->where('type', 'tool_contract')->exists())->toBeTrue();
});

test('switching a db tool to another mode clears its data source mapping', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create();
    $tool = ToolContract::factory()->for($team)->create([
        'execution_mode' => ExecMode::Db,
        'data_source_id' => $source->id,
        'db_config' => ['query' => 'select 1 from reporting_port_calls', 'bindings' => []],
    ]);

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $team->slug, 'tool' => $tool->slug]), [
            'execution_mode' => 'hosted',
        ])
        ->assertRedirect();

    $fresh = $tool->fresh();
    expect($fresh->data_source_id)->toBeNull()
        ->and($fresh->db_config)->toBeNull();
});

test('a data source can bind a vault credential', function () {
    [$owner, $team] = ownerAndTeam();
    $secret = app(SecretVault::class)->store($team, 'database:reporting', 'Reporting Credential', VaultSecretKind::Database, 'replica-secret', $owner);

    $this->actingAs($owner)
        ->post(route('data-sources.store', ['current_team' => $team->slug]), [
            'name' => 'Vaulted Replica',
            'connection_type' => 'read_replica',
            'connection' => 'maac_reporting',
            'vault_secret_id' => $secret->id,
            'sensitivity' => 'internal',
            'environments' => ['production'],
            'allowed_relations' => ['reporting_port_calls'],
        ])
        ->assertRedirect();

    expect(DataSource::firstWhere('name', 'Vaulted Replica')->vault_secret_id)->toBe($secret->id);
});
