<?php

namespace App\Http\Controllers\Maac;

use App\Concerns\RecordsMaacAudit;
use App\Enums\AppStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreApplicationRequest;
use App\Http\Requests\Maac\UpdateApplicationRequest;
use App\Models\Application;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    use RecordsMaacAudit;

    /**
     * Register a new application for the current team.
     */
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        Gate::authorize('create', Application::class);

        $team = $request->user()->currentTeam;

        if ($team === null) {
            abort(403);
        }

        $validated = $request->validated();

        $application = Application::create([
            ...$validated,
            'team_id' => $team->id,
            'slug' => Slug::unique('applications', $request->string('code')->value()),
            'status' => $validated['status'] ?? AppStatus::Active->value,
        ]);

        $this->recordAudit($request, 'application.registered', $application, ['name' => $application->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application registered.']);

        return back();
    }

    /**
     * Update the given application.
     */
    public function update(UpdateApplicationRequest $request, string $currentTeam, Application $application): RedirectResponse
    {
        Gate::authorize('update', $application);

        $application->update($request->validated());

        $this->recordAudit($request, 'application.updated', $application);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application updated.']);

        return back();
    }

    /**
     * Archive (soft delete) the given application.
     */
    public function destroy(Request $request, string $currentTeam, Application $application): RedirectResponse
    {
        Gate::authorize('delete', $application);

        $application->delete();

        $this->recordAudit($request, 'application.deleted', $application);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application archived.']);

        return back();
    }
}
