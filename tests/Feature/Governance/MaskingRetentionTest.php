<?php

use App\Enums\Environment;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\ToolCall;
use App\Support\Governance\PayloadMasker;
use App\Support\Governance\RetentionPruner;
use App\Support\Governance\RunRedactor;

test('the run redactor masks confidential payloads and passes through others', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create();
    $agent = maacAgent($team);
    $confidential = maacRun($agent, ['sensitivity' => Sensitivity::Confidential, 'environment' => Environment::Production]);
    $internal = maacRun($agent, ['sensitivity' => Sensitivity::Internal, 'environment' => Environment::Production]);

    $redactor = app(RunRedactor::class);

    expect($redactor->input($confidential, 'secret prompt'))->toBe(PayloadMasker::REDACTED)
        ->and($redactor->result($confidential, ['amount' => 1000]))->toBe(['amount' => PayloadMasker::REDACTED])
        ->and($redactor->applies($confidential))->toBeTrue()
        ->and($redactor->input($internal, 'plain prompt'))->toBe('plain prompt')
        ->and($redactor->applies($internal))->toBeFalse();
});

test('per-environment masking overrides disable redaction', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create([
        'environment_overrides' => [
            'production' => ['mask_sensitive_inputs' => false, 'mask_sensitive_outputs' => false, 'block_restricted_logging' => false],
        ],
    ]);
    $agent = maacAgent($team);
    $run = maacRun($agent, ['sensitivity' => Sensitivity::Confidential, 'environment' => Environment::Production]);

    $redactor = app(RunRedactor::class);

    expect($redactor->input($run, 'secret'))->toBe('secret')
        ->and($redactor->applies($run))->toBeFalse();
});

test('the retention pruner redacts old run payloads and deletes old audit events', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create([
        'retain_prompts_days' => 10,
        'retain_responses_days' => 10,
        'retain_tool_arguments_days' => 10,
        'retain_tool_results_days' => 10,
        'audit_retention_days' => 30,
    ]);
    $agent = maacAgent($team);

    $old = maacRun($agent, [
        'status' => RunStatus::Completed,
        'environment' => Environment::Production,
        'input' => 'old prompt',
        'output' => 'old response',
        'state' => ['messages' => []],
        'completed_at' => now()->subDays(40),
    ]);
    $oldCall = ToolCall::factory()->for($old)->create(['arguments' => ['a' => 1], 'result' => ['b' => 2], 'completed_at' => now()->subDays(40)]);

    $recent = maacRun($agent, [
        'status' => RunStatus::Completed,
        'environment' => Environment::Production,
        'input' => 'recent prompt',
        'completed_at' => now()->subDay(),
    ]);

    $oldAudit = $this->travelTo(now()->subDays(40), fn () => AuditEvent::factory()->create(['team_id' => $team->id]));
    $newAudit = AuditEvent::factory()->create(['team_id' => $team->id]);

    $result = app(RetentionPruner::class)->prune();

    expect($old->fresh()->input)->toBeNull()
        ->and($old->fresh()->output)->toBeNull()
        ->and($old->fresh()->state)->toBeNull()
        ->and($oldCall->fresh()->arguments)->toBeNull()
        ->and($oldCall->fresh()->result)->toBeNull()
        ->and($recent->fresh()->input)->toBe('recent prompt')
        ->and(AuditEvent::find($oldAudit->id))->toBeNull()
        ->and(AuditEvent::find($newAudit->id))->not->toBeNull()
        ->and($result['runs'])->toBeGreaterThan(0)
        ->and($result['audits'])->toBe(1);
});

test('retention windows can be separated per environment', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create([
        'retain_prompts_days' => 365,
        'environment_overrides' => ['production' => ['retain_prompts_days' => 5]],
    ]);
    $agent = maacAgent($team);

    $production = maacRun($agent, ['status' => RunStatus::Completed, 'environment' => Environment::Production, 'input' => 'prod', 'completed_at' => now()->subDays(10)]);
    $staging = maacRun($agent, ['status' => RunStatus::Completed, 'environment' => Environment::Staging, 'input' => 'staging', 'completed_at' => now()->subDays(10)]);

    app(RetentionPruner::class)->prune();

    expect($production->fresh()->input)->toBeNull()
        ->and($staging->fresh()->input)->toBe('staging');
});

test('the prune command reports its work', function () {
    [, $team] = ownerAndTeam();
    GovernanceSetting::factory()->for($team)->create(['retain_prompts_days' => 1]);
    $agent = maacAgent($team);
    $old = maacRun($agent, ['status' => RunStatus::Completed, 'environment' => Environment::Production, 'input' => 'x', 'completed_at' => now()->subDays(5)]);

    $this->artisan('maac:prune-run-data')->assertExitCode(0);

    expect($old->fresh()->input)->toBeNull();
});
