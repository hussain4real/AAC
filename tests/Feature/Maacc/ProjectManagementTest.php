<?php

use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;

test('a platform admin can create a project under an application', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $llm = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('projects.store', ['current_team' => $team->slug]), [
            'application_id' => $application->id,
            'name' => 'Fleet Intelligence',
            'environment' => 'production',
            'llm_provider_ids' => [$llm->id],
        ])
        ->assertRedirect();

    $project = Project::firstWhere('name', 'Fleet Intelligence');

    expect($project)->not->toBeNull()
        ->and($project->application_id)->toBe($application->id)
        ->and($project->llmProviders()->pluck('llm_providers.id')->all())->toBe([$llm->id]);
});

test('project creation validates required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('projects.store', ['current_team' => $team->slug]), [])
        ->assertSessionHasErrors(['application_id', 'name', 'environment']);
});

test('a non-admin cannot create a project', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $application = Application::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('projects.store', ['current_team' => $team->slug]), [
            'application_id' => $application->id,
            'name' => 'Blocked',
            'environment' => 'production',
        ])
        ->assertForbidden();
});

test('a platform admin can update and archive a project', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();

    $this->actingAs($owner)
        ->put(route('projects.update', ['current_team' => $team->slug, 'project' => $project->slug]), [
            'name' => 'Renamed Project',
        ])
        ->assertRedirect();

    expect($project->fresh()->name)->toBe('Renamed Project');

    $this->actingAs($owner)
        ->delete(route('projects.destroy', ['current_team' => $team->slug, 'project' => $project->slug]))
        ->assertRedirect();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});
