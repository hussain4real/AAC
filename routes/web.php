<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Maac\AgentController;
use App\Http\Controllers\Maac\ApplicationController;
use App\Http\Controllers\Maac\ConsoleController;
use App\Http\Controllers\Maac\CredentialController;
use App\Http\Controllers\Maac\LlmProviderController;
use App\Http\Controllers\Maac\ProjectController;
use App\Http\Controllers\Maac\ToolContractController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // MAAC console (Phase 1 — mock-backed)
        Route::get('applications', [ConsoleController::class, 'applications'])->name('applications');
        Route::get('applications/{application}', [ConsoleController::class, 'application'])->name('applications.show');
        Route::get('projects', [ConsoleController::class, 'projects'])->name('projects');
        Route::get('agents', [ConsoleController::class, 'agents'])->name('agents');
        Route::get('agents/create', [ConsoleController::class, 'createAgent'])->name('agents.create');
        Route::get('agents/{agent}', [ConsoleController::class, 'agent'])->name('agents.show');
        Route::get('tools', [ConsoleController::class, 'tools'])->name('tools');
        Route::get('tools/{tool}', [ConsoleController::class, 'tool'])->name('tools.show');
        Route::get('sdk', [ConsoleController::class, 'sdk'])->name('sdk');
        Route::get('playground', [ConsoleController::class, 'playground'])->name('playground');
        Route::get('runs', [ConsoleController::class, 'runs'])->name('runs');
        Route::get('runs/{run}', [ConsoleController::class, 'run'])->name('runs.show');
        Route::get('llm-providers', [ConsoleController::class, 'llmProviders'])->name('llm-providers');
        Route::get('governance', [ConsoleController::class, 'governance'])->name('governance');
        Route::get('platform-settings', [ConsoleController::class, 'settings'])->name('platform-settings');

        // MAAC console (Phase 2 — database-backed writes)
        Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::put('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
        Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

        Route::post('applications/{application}/credentials', [CredentialController::class, 'store'])->name('applications.credentials.store');
        Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
        Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');

        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::post('agents/{agent}/publish', [AgentController::class, 'publish'])->name('agents.publish');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');

        Route::post('tools', [ToolContractController::class, 'store'])->name('tools.store');
        Route::put('tools/{tool}', [ToolContractController::class, 'update'])->name('tools.update');
        Route::delete('tools/{tool}', [ToolContractController::class, 'destroy'])->name('tools.destroy');

        Route::post('llm-providers', [LlmProviderController::class, 'store'])->name('llm-providers.store');
        Route::put('llm-providers/{llmProvider}', [LlmProviderController::class, 'update'])->name('llm-providers.update');
        Route::delete('llm-providers/{llmProvider}', [LlmProviderController::class, 'destroy'])->name('llm-providers.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
