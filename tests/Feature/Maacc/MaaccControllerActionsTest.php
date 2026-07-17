<?php

use App\Actions\Maacc\ArchiveApplication;
use App\Actions\Maacc\ArchiveProject;
use App\Actions\Maacc\CreateAgent;
use App\Actions\Maacc\CreateApplication;
use App\Actions\Maacc\CreateCredential;
use App\Actions\Maacc\CreateLlmProvider;
use App\Actions\Maacc\CreateProject;
use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\DeleteAgent;
use App\Actions\Maacc\DeleteLlmProvider;
use App\Actions\Maacc\DeleteToolContract;
use App\Actions\Maacc\PublishAgent;
use App\Actions\Maacc\RevokeCredential;
use App\Actions\Maacc\RotateCredential;
use App\Actions\Maacc\SyncAgentTools;
use App\Actions\Maacc\UpdateAgent;
use App\Actions\Maacc\UpdateApplication;
use App\Actions\Maacc\UpdateLlmProvider;
use App\Actions\Maacc\UpdateProject;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\Environment;
use App\Models\Agent;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;

test('maacc phase 2 write use cases are implemented as actions', function () {
    $actions = [
        ArchiveApplication::class,
        ArchiveProject::class,
        CreateAgent::class,
        CreateApplication::class,
        CreateCredential::class,
        CreateLlmProvider::class,
        CreateProject::class,
        CreateToolContract::class,
        DeleteAgent::class,
        DeleteLlmProvider::class,
        DeleteToolContract::class,
        PublishAgent::class,
        RevokeCredential::class,
        RotateCredential::class,
        SyncAgentTools::class,
        UpdateAgent::class,
        UpdateApplication::class,
        UpdateLlmProvider::class,
        UpdateProject::class,
        UpdateToolContract::class,
    ];

    foreach ($actions as $action) {
        expect((new ReflectionClass($action))->hasMethod('handle'))->toBeTrue();
    }
});

test('maacc write controllers delegate persistence to actions', function () {
    $controllers = [
        app_path('Http/Controllers/Maacc/ApplicationController.php'),
        app_path('Http/Controllers/Maacc/ProjectController.php'),
        app_path('Http/Controllers/Maacc/AgentController.php'),
        app_path('Http/Controllers/Maacc/CredentialController.php'),
        app_path('Http/Controllers/Maacc/ToolContractController.php'),
        app_path('Http/Controllers/Maacc/LlmProviderController.php'),
    ];

    foreach ($controllers as $controller) {
        $source = (string) file_get_contents($controller);

        expect($source)
            ->not->toContain('::create(')
            ->not->toContain('->update(')
            ->not->toContain('->delete(')
            ->not->toContain('->save(');
    }
});

test('a platform admin can update an agent and re-sync its tools', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $llm = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($llm);
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();
    $tool = ToolContract::factory()->for($team)->for($application)->create();

    $this->actingAs($owner)
        ->put(route('agents.update', ['current_team' => $team->slug, 'agent' => $agent->slug]), [
            'name' => 'Renamed Agent',
            'tool_ids' => [$tool->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $agent->refresh();
    expect($agent->name)->toBe('Renamed Agent')
        ->and($agent->tools()->pluck('tool_contracts.id')->all())->toBe([$tool->id]);
});

test('a platform admin can delete an agent', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $llm = LlmProvider::factory()->for($team)->create();
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create();

    $this->actingAs($owner)
        ->delete(route('agents.destroy', ['current_team' => $team->slug, 'agent' => $agent->slug]))
        ->assertRedirect();

    $this->assertSoftDeleted('agents', ['id' => $agent->id]);
});

test('a platform admin can remove a model from the catalog', function () {
    [$owner, $team] = ownerAndTeam();
    $llm = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->delete(route('llm-providers.destroy', ['current_team' => $team->slug, 'llmProvider' => $llm->slug]))
        ->assertRedirect();

    $this->assertDatabaseMissing('llm_providers', ['id' => $llm->id]);
});

test('a platform admin can delete a tool contract', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create();

    $this->actingAs($owner)
        ->delete(route('tools.destroy', ['current_team' => $team->slug, 'tool' => $tool->slug]))
        ->assertRedirect();

    $this->assertSoftDeleted('tool_contracts', ['id' => $tool->id]);
});

test('updating a project re-syncs its approved models', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $llm = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('projects.update', ['current_team' => $team->slug, 'project' => $project->slug]), [
            'name' => 'Synced Project',
            'llm_provider_ids' => [$llm->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($project->fresh()->llmProviders()->pluck('llm_providers.id')->all())->toBe([$llm->id]);
});
