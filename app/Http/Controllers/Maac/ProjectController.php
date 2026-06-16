<?php

namespace App\Http\Controllers\Maac;

use App\Concerns\RecordsMaacAudit;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreProjectRequest;
use App\Http\Requests\Maac\UpdateProjectRequest;
use App\Models\Project;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    use RecordsMaacAudit;

    /**
     * Create a new project under an application.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $validated = $request->validated();

        $project = Project::create([
            ...$validated,
            'slug' => Slug::unique('projects', $request->string('name')->value()),
            'status' => $validated['status'] ?? ProjectStatus::Active->value,
        ]);

        if ($request->has('llm_provider_ids')) {
            $project->llmProviders()->sync($request->collect('llm_provider_ids')->all());
        }

        $this->recordAudit($request, 'project.created', $project, ['name' => $project->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project created.']);

        return back();
    }

    /**
     * Update the given project.
     */
    public function update(UpdateProjectRequest $request, string $currentTeam, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        if ($request->has('llm_provider_ids')) {
            $project->llmProviders()->sync($request->collect('llm_provider_ids')->all());
        }

        $this->recordAudit($request, 'project.updated', $project);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return back();
    }

    /**
     * Archive (soft delete) the given project.
     */
    public function destroy(Request $request, string $currentTeam, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        $this->recordAudit($request, 'project.deleted', $project);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project archived.']);

        return back();
    }
}
