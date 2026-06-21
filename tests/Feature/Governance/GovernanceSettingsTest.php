<?php

use App\Enums\Environment;
use App\Enums\MaacRole;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\Project;

/**
 * A valid governance settings update payload.
 *
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'retain_prompts_days' => 7,
        'retain_responses_days' => 14,
        'retain_tool_arguments_days' => 5,
        'retain_tool_results_days' => 5,
        'audit_retention_days' => 180,
        'mask_sensitive_inputs' => true,
        'mask_sensitive_outputs' => false,
        'block_restricted_logging' => true,
        'default_daily_run_quota' => 1000,
    ], $overrides);
}

test('a platform admin can update governance settings and it is audited', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->put(route('governance-settings.update', ['current_team' => $team->slug]), settingsPayload())
        ->assertRedirect();

    $settings = GovernanceSetting::forTeam($team->fresh());

    expect($settings->exists)->toBeTrue()
        ->and($settings->retain_prompts_days)->toBe(7)
        ->and($settings->mask_sensitive_outputs)->toBeFalse()
        ->and($settings->default_daily_run_quota)->toBe(1000)
        ->and(AuditEvent::where('team_id', $team->id)->where('action', 'governance_settings.updated')->exists())->toBeTrue();
});

test('a developer cannot update governance settings', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $developer = projectRoleUser($team, $project, MaacRole::Developer);

    $this->actingAs($developer)
        ->put(route('governance-settings.update', ['current_team' => $team->slug]), settingsPayload())
        ->assertForbidden();
});

test('settings update validates retention bounds', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->put(route('governance-settings.update', ['current_team' => $team->slug]), settingsPayload([
            'retain_prompts_days' => 0,
            'audit_retention_days' => 99999,
        ]))
        ->assertSessionHasErrors(['retain_prompts_days', 'audit_retention_days']);
});

test('settings resolve to a default unsaved instance and report retention per field', function () {
    [, $team] = ownerAndTeam();
    $settings = GovernanceSetting::forTeam($team);

    expect($settings->exists)->toBeFalse()
        ->and($settings->retentionDaysFor('prompts'))->toBe(90)
        ->and($settings->retentionDaysFor('responses'))->toBe(90)
        ->and($settings->retentionDaysFor('tool_arguments'))->toBe(30)
        ->and($settings->retentionDaysFor('tool_results'))->toBe(30)
        ->and($settings->retentionDaysFor('audit'))->toBe(365)
        ->and($settings->masksInputs())->toBeTrue()
        ->and($settings->masksOutputs())->toBeTrue()
        ->and($settings->blocksRestrictedLogging())->toBeTrue()
        ->and($settings->dailyRunQuota())->toBeNull();
});

test('per-environment overrides take precedence over base settings', function () {
    [, $team] = ownerAndTeam();
    $settings = GovernanceSetting::factory()->for($team)->create([
        'environment_overrides' => [
            'production' => [
                'retain_prompts_days' => 3,
                'mask_sensitive_inputs' => false,
                'block_restricted_logging' => false,
                'default_daily_run_quota' => 50,
            ],
        ],
    ]);

    expect($settings->retentionDaysFor('prompts', Environment::Production))->toBe(3)
        ->and($settings->retentionDaysFor('prompts', Environment::Staging))->toBe(90)
        ->and($settings->masksInputs(Environment::Production))->toBeFalse()
        ->and($settings->masksInputs(Environment::Staging))->toBeTrue()
        ->and($settings->blocksRestrictedLogging(Environment::Production))->toBeFalse()
        ->and($settings->dailyRunQuota(Environment::Production))->toBe(50)
        ->and($settings->dailyRunQuota(Environment::Staging))->toBeNull();
});
