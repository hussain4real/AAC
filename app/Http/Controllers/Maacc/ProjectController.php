<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\ArchiveProject;
use App\Actions\Maacc\CreateProject;
use App\Actions\Maacc\UpdateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreProjectRequest;
use App\Http\Requests\Maacc\UpdateProjectRequest;
use App\Models\Application;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Create a new project under an application.
     */
    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $application = Application::query()
            ->whereKey($request->string('application_id')->value())
            ->where('team_id', $team->id)
            ->firstOrFail();

        Gate::authorize('create', [Project::class, $application]);

        $createProject->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project created.']);

        return back();
    }

    /**
     * Update the given project.
     */
    public function update(UpdateProjectRequest $request, string $currentTeam, Project $project, UpdateProject $updateProject): RedirectResponse
    {
        Gate::authorize('update', $project);

        $updateProject->handle($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return back();
    }

    /**
     * Archive (soft delete) the given project.
     */
    public function destroy(Request $request, string $currentTeam, Project $project, ArchiveProject $archiveProject): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $archiveProject->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project archived.']);

        return back();
    }
}
