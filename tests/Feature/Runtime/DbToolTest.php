<?php

use App\Enums\DataSourceStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Enums\TraceEventType;
use App\Enums\VaultSecretKind;
use App\Models\Agent;
use App\Models\Application;
use App\Models\DataSource;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\Db\DbToolExecutor;
use App\Support\Runtime\ToolExecutionException;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();

    Schema::create('reporting_metrics', function ($table) {
        $table->increments('id');
        $table->string('region');
        $table->string('vessel');
        $table->integer('calls');
        $table->string('secret_note')->nullable();
    });

    DB::table('reporting_metrics')->insert([
        ['region' => 'EU', 'vessel' => 'Milaha Ras Laffan', 'calls' => 12, 'secret_note' => 'internal-only'],
        ['region' => 'EU', 'vessel' => 'Milaha Doha', 'calls' => 9, 'secret_note' => 'internal-only'],
        ['region' => 'APAC', 'vessel' => 'Milaha Qatar', 'calls' => 4, 'secret_note' => 'internal-only'],
    ]);

    $this->source = DataSource::factory()->for($this->team)->create([
        'application_id' => null,
        'status' => DataSourceStatus::Active,
        'environments' => [Environment::Production->value],
        'connection' => (string) config('database.default'),
        'allowed_relations' => ['reporting_metrics'],
        'max_rows' => 100,
        'max_result_kb' => 256,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('reporting_metrics');
});

function dbTool(DataSource $source, array $overrides = []): ToolContract
{
    return ToolContract::factory()->for($source->team)->create(array_merge([
        'application_id' => null,
        'scope' => ToolScope::Global,
        'execution_mode' => ExecMode::Db,
        'data_source_id' => $source->id,
        'db_config' => [
            'query' => 'select region, vessel, calls from reporting_metrics where region = :region order by calls desc',
            'bindings' => ['region'],
            'columns' => ['vessel', 'calls'],
            'row_limit' => 50,
            'max_age_minutes' => null,
        ],
        'input_schema' => ['region' => 'string'],
        'output_schema' => ['rows' => 'array', 'row_count' => 'integer'],
    ], $overrides));
}

it('runs a governed read-only query and returns minimized rows', function () {
    $result = app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        ['region' => 'EU'],
    );

    expect($result['row_count'])->toBe(2)
        ->and($result['rows'][0])->toBe(['vessel' => 'Milaha Ras Laffan', 'calls' => 12])
        // Result minimization: secret_note + id are not in the projected columns.
        ->and($result['rows'][0])->not->toHaveKey('secret_note')
        ->and($result['rows'][0])->not->toHaveKey('region');
});

it('returns the full row when no projection columns are configured', function () {
    $result = app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select region, vessel from reporting_metrics where region = :region',
            'bindings' => ['region'],
            'columns' => [],
            'row_limit' => 5,
        ]]),
        Environment::Production,
        ['region' => 'APAC'],
    );

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0])->toHaveKey('region')
        ->and($result['rows'][0])->toHaveKey('vessel');
});

it('binds parameters safely so an injection value matches nothing', function () {
    $result = app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        ['region' => "EU' OR '1'='1"],
    );

    expect($result['row_count'])->toBe(0);
});

it('caps the result at the governed row limit', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel from reporting_metrics where calls > :min',
            'bindings' => ['min'],
            'columns' => ['vessel'],
            'row_limit' => 1,
        ]]),
        Environment::Production,
        ['min' => 0],
    ))->toThrow(ToolExecutionException::class, '1-row limit');
});

it('rejects a non-read-only statement', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'update reporting_metrics set calls = 0',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'read-only SELECT');
});

it('rejects a query with an embedded write keyword', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel from reporting_metrics; drop table reporting_metrics',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'single statement');
});

it('rejects a query that references a relation outside the approved surface', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select * from users',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'not on the approved query surface');
});

it('rejects a query that contains a SQL comment', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel from reporting_metrics -- sneaky',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'must not contain SQL comments');
});

it('rejects an empty query', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => ['query' => '   ', 'bindings' => []]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'query is empty');
});

it('rejects a single-statement query that hides a write keyword after a CTE', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'with t as (select 1) delete from reporting_metrics',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'disallowed keyword [delete]');
});

it('rejects a query that does not read from an approved relation', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => ['query' => 'select 1', 'bindings' => []]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'does not read from an approved relation');
});

it('fails with a controlled code when the connection cannot be established', function () {
    config(['database.connections.broken_replica' => [
        'driver' => 'sqlite',
        'database' => '/nonexistent-maac-dir/missing.sqlite',
        'foreign_key_constraints' => false,
    ]]);
    $this->source->update(['connection' => 'broken_replica']);

    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel from reporting_metrics',
            'bindings' => [],
            'columns' => ['vessel'],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'connection failed');
});

it('does not flag a write keyword that only appears inside a string literal', function () {
    DB::table('reporting_metrics')->insert(['region' => 'deleted', 'vessel' => 'Ghost', 'calls' => 1]);

    $result = app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => "select vessel from reporting_metrics where region = 'deleted'",
            'bindings' => [],
            'columns' => ['vessel'],
        ]]),
        Environment::Production,
        [],
    );

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0]['vessel'])->toBe('Ghost');
});

it('fails when the tool is not mapped to a data source', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['data_source_id' => null]),
        Environment::Production,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'not mapped to a data source');
});

it('fails when the source is disabled', function () {
    $this->source->update(['status' => DataSourceStatus::Disabled]);

    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'disabled or not available');
});

it('fails when the source is not available in the environment', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Staging,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'disabled or not available');
});

it('fails when the source data is stale', function () {
    $this->source->update([
        'data_refreshed_at' => now()->subHours(5),
        'staleness_threshold_minutes' => 60,
    ]);

    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'stale');
});

it('fails when the referenced connection is not configured', function () {
    $this->source->update(['connection' => 'nonexistent_replica']);

    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'unconfigured connection');
});

it('fails when a declared binding is missing from the arguments', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'missing the bound argument');
});

it('fails when the query is invalid against the connection', function () {
    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select nonexistent_column from reporting_metrics',
            'bindings' => [],
        ]]),
        Environment::Production,
        [],
    ))->toThrow(ToolExecutionException::class, 'query failed');
});

it('rejects a result that exceeds the size limit', function () {
    $this->source->update(['max_result_kb' => 1, 'max_rows' => 5000]);
    DB::table('reporting_metrics')->insert(collect(range(1, 200))->map(fn ($i) => [
        'region' => 'EU',
        'vessel' => 'Vessel '.str_repeat('x', 40).$i,
        'calls' => $i,
    ])->all());

    expect(fn () => app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel, calls from reporting_metrics where region = :region',
            'bindings' => ['region'],
            'columns' => ['vessel', 'calls'],
            'row_limit' => 5000,
        ]]),
        Environment::Production,
        ['region' => 'EU'],
    ))->toThrow(ToolExecutionException::class, 'result-size limit');
});

it('resolves the connection credential from the secrets vault', function () {
    $path = tempnam(sys_get_temp_dir(), 'maac_ds_').'.sqlite';
    touch($path);
    config(['database.connections.reporting_file' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => false]]);
    DB::connection('reporting_file')->statement('create table reporting_metrics (id integer primary key, region text, vessel text, calls integer)');
    DB::connection('reporting_file')->table('reporting_metrics')->insert(['region' => 'EU', 'vessel' => 'Vault Vessel', 'calls' => 7]);

    $secret = app(SecretVault::class)->store($this->team, 'database:test', 'Reporting DB', VaultSecretKind::Database, 'replica-secret');
    $this->source->update(['connection' => 'reporting_file', 'vault_secret_id' => $secret->id]);

    $result = app(DbToolExecutor::class)->execute(
        dbTool($this->source, ['db_config' => [
            'query' => 'select vessel from reporting_metrics where region = :region',
            'bindings' => ['region'],
            'columns' => ['vessel'],
        ]]),
        Environment::Production,
        ['region' => 'EU'],
    );

    expect($result['rows'][0]['vessel'])->toBe('Vault Vessel')
        ->and($secret->fresh()->accessed_count)->toBeGreaterThan(0);

    @unlink($path);
});

it('drives a full db-tool run through the runtime with trace and row data', function () {
    $tool = dbTool($this->source, ['slug' => 'regionMetrics']);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create([
        'environments' => [Environment::Production->value],
        'input_cost' => 1.0,
        'output_cost' => 2.0,
    ]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()
        ->toolCallThen('regionMetrics', ['region' => 'EU'])
        ->textThen('EU had 21 vessel calls across two vessels.');

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'How many calls in EU?', 'tester');

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toContain('21');

    $call = $run->toolCalls()->where('tool_name', 'regionMetrics')->first();
    expect($call->result['row_count'])->toBe(2)
        ->and($run->tokens_in)->toBeGreaterThan(0)
        ->and($run->traceEvents()->where('type', TraceEventType::ToolResultReceived)->exists())->toBeTrue();
});

it('fails the run when a db tool requires approval but is not active', function () {
    $tool = dbTool($this->source, [
        'slug' => 'gatedDb',
        'requires_approval' => true,
        'status' => 'Draft',
        'sensitivity' => Sensitivity::Confidential,
    ]);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create(['environments' => [Environment::Production->value]]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('gatedDb', ['region' => 'EU']);

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'q', 'tester');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('tool_requires_approval');
});

it('fails the run with a controlled code when the db source is unavailable', function () {
    $this->source->update(['status' => DataSourceStatus::Disabled]);
    $tool = dbTool($this->source, ['slug' => 'disabledDb']);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create(['environments' => [Environment::Production->value]]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('disabledDb', ['region' => 'EU']);

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'q', 'tester');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('db_source_unavailable');
});

it('fails the run with db_invalid_output when the result violates the output schema', function () {
    $tool = dbTool($this->source, [
        'slug' => 'badSchemaDb',
        'output_schema' => ['rows' => 'array', 'row_count' => 'string'],
    ]);

    $application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($this->team)->create(['environments' => [Environment::Production->value]]);
    $agent = Agent::factory()->for($project)->for($model)->published()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    bindFakeRouter()->toolCallThen('badSchemaDb', ['region' => 'EU']);

    $run = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'q', 'tester');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('db_invalid_output');
});
