<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\DataSourceStatus;
use App\Http\Resources\Maac\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\ToolContract;
use App\Models\User;
use App\Policies\DataSourcePolicy;
use App\Support\Governance\ApprovalManager;

test('requesting data source access opens an idempotent pending approval', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->sensitive()->create();

    $first = app(ApprovalManager::class)->requestDataSourceAccess($source, $owner);
    $second = app(ApprovalManager::class)->requestDataSourceAccess($source, $owner);

    expect($first->is($second))->toBeTrue()
        ->and($first->type)->toBe(ApprovalType::DataSourceAccess)
        ->and($first->status)->toBe(ApprovalStatus::Pending)
        ->and($first->type->queue())->toBe('data')
        ->and($first->sensitivity->value)->toBe('confidential');
});

test('approving a data source access request activates the source', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->draft()->sensitive()->create();
    $request = app(ApprovalManager::class)->requestDataSourceAccess($source, $owner);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($source->fresh()->status)->toBe(DataSourceStatus::Active);
});

test('the approval request resource builds a data source detail view', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->sensitive()->allowing(['reporting_port_calls'])->create(['name' => 'Finance Replica']);
    $request = app(ApprovalManager::class)->requestDataSourceAccess($source, $owner);

    $payload = (new ApprovalRequestResource($request->load('subject')))->toArray(request());

    expect($payload['subject']['kind'])->toBe('Data source')
        ->and(collect($payload['subject']['fields'])->firstWhere('k', 'Sensitivity')['v'])->toBe('Confidential')
        ->and(collect($payload['subject']['fields'])->firstWhere('k', 'Query surface')['v'])->toBe('reporting_port_calls')
        ->and(collect($payload['subject']['fields'])->firstWhere('k', 'Credential')['v'])->toBe('Connection-managed');
});

test('the approval request resource surfaces a db tool query surface for egress review', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->allowing(['reporting_port_calls'])->create(['name' => 'Reporting Replica']);
    $tool = ToolContract::factory()->for($team)->db($source, [
        'query' => 'select port from reporting_port_calls where region = :region',
        'bindings' => ['region'],
    ])->create([
        'requires_approval' => true,
        'status' => 'Draft',
        'redaction' => ['port'],
    ]);
    $request = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    $payload = (new ApprovalRequestResource($request->load('subject')))->toArray(request());
    $fields = collect($payload['subject']['fields']);

    expect($payload['subject']['kind'])->toBe('Tool contract')
        ->and($fields->firstWhere('k', 'Execution mode')['v'])->toBe('Read-only DB')
        ->and($fields->firstWhere('k', 'Data source')['v'])->toBe('Reporting Replica')
        ->and($fields->firstWhere('k', 'Query surface')['v'])->toBe('reporting_port_calls')
        ->and($fields->firstWhere('k', 'Query')['v'])->toContain('select port from reporting_port_calls')
        ->and($fields->firstWhere('k', 'Redacted fields')['v'])->toBe('port');
});

test('a data source access approval can be opened through the governance endpoint', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->draft()->create();

    $this->actingAs($owner)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => 'data_source_access',
            'subject' => $source->slug,
        ])
        ->assertRedirect();

    expect(ApprovalRequest::where('subject_id', $source->id)->where('type', 'data_source_access')->exists())->toBeTrue();
});

test('the data source policy authorizes team tool managers', function () {
    [$owner, $team] = ownerAndTeam();
    $source = DataSource::factory()->for($team)->create();
    $outsider = User::factory()->create();
    $policy = new DataSourcePolicy;

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->view($owner, $source))->toBeTrue()
        ->and($policy->view($outsider, $source))->toBeFalse()
        ->and($policy->create($owner))->toBeTrue()
        ->and($policy->update($owner, $source))->toBeTrue()
        ->and($policy->delete($owner, $source))->toBeTrue();
});
