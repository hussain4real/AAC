<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\ArchiveApplication;
use App\Actions\Maac\CreateApplication;
use App\Actions\Maac\UpdateApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreApplicationRequest;
use App\Http\Requests\Maac\UpdateApplicationRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    /**
     * Register a new application for the current team.
     */
    public function store(StoreApplicationRequest $request, CreateApplication $createApplication): RedirectResponse
    {
        Gate::authorize('create', Application::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $createApplication->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application registered.']);

        return back();
    }

    /**
     * Update the given application.
     */
    public function update(UpdateApplicationRequest $request, string $currentTeam, Application $application, UpdateApplication $updateApplication): RedirectResponse
    {
        Gate::authorize('update', $application);

        $updateApplication->handle($application, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application updated.']);

        return back();
    }

    /**
     * Archive (soft delete) the given application.
     */
    public function destroy(Request $request, string $currentTeam, Application $application, ArchiveApplication $archiveApplication): RedirectResponse
    {
        Gate::authorize('delete', $application);

        $archiveApplication->handle($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application archived.']);

        return back();
    }
}
