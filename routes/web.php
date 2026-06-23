<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Maac\AgentController;
use App\Http\Controllers\Maac\ApplicationController;
use App\Http\Controllers\Maac\ApprovalRequestController;
use App\Http\Controllers\Maac\ConsoleController;
use App\Http\Controllers\Maac\CredentialController;
use App\Http\Controllers\Maac\GovernanceSettingController;
use App\Http\Controllers\Maac\LlmProviderController;
use App\Http\Controllers\Maac\McpConnectorController;
use App\Http\Controllers\Maac\ProjectController;
use App\Http\Controllers\Maac\QuotaLimitController;
use App\Http\Controllers\Maac\ToolContractController;
use App\Http\Controllers\Maac\WebhookDeliveryController;
use App\Http\Controllers\Maac\WebhookEndpointController;
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
        Route::get('sdk/docs', [ConsoleController::class, 'sdkDocs'])->name('sdk.docs');
        Route::get('playground', [ConsoleController::class, 'playground'])->name('playground');
        Route::get('runs', [ConsoleController::class, 'runs'])->name('runs');
        Route::get('runs/{run}', [ConsoleController::class, 'run'])->name('runs.show');
        Route::get('llm-providers', [ConsoleController::class, 'llmProviders'])->name('llm-providers');
        Route::get('connectors', [ConsoleController::class, 'connectors'])->name('connectors');
        Route::get('governance', [ConsoleController::class, 'governance'])->name('governance');
        Route::get('webhooks', [ConsoleController::class, 'webhooks'])->name('webhooks');
        Route::get('platform-settings', [ConsoleController::class, 'settings'])->name('platform-settings');

        // MAAC console (Phase 2 — database-backed writes)
        Route::post('applications/{application}/credentials', [CredentialController::class, 'store'])->name('applications.credentials.store');
        Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
        Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');

        Route::post('agents/{agent}/publish', [AgentController::class, 'publish'])->name('agents.publish');

        Route::resource('applications', ApplicationController::class)->only(['store', 'update', 'destroy']);
        Route::resource('projects', ProjectController::class)->only(['store', 'update', 'destroy']);
        Route::resource('agents', AgentController::class)->only(['store', 'update', 'destroy']);
        Route::resource('tools', ToolContractController::class)->only(['store', 'update', 'destroy']);
        Route::resource('llm-providers', LlmProviderController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['llm-providers' => 'llmProvider']);

        // MAAC console (Phase 6E — MCP connectors for connector-backed tools)
        Route::resource('connectors', McpConnectorController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['connectors' => 'mcpConnector']);
        Route::post('connectors/{mcpConnector}/discover', [McpConnectorController::class, 'discover'])->name('connectors.discover');

        // MAAC console (Phase 5 — governance & security hardening)
        Route::post('approvals', [ApprovalRequestController::class, 'store'])->name('approvals.store');
        Route::post('approvals/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->name('approvals.reject');
        Route::put('governance-settings', [GovernanceSettingController::class, 'update'])->name('governance-settings.update');
        Route::resource('quotas', QuotaLimitController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['quotas' => 'quotaLimit']);

        // MAAC console (Phase 6D — webhook endpoints & delivery observability)
        Route::resource('webhooks', WebhookEndpointController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['webhooks' => 'webhookEndpoint']);
        Route::post('webhooks/{webhookEndpoint}/rotate', [WebhookEndpointController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('webhook-deliveries/{webhookDelivery}/replay', [WebhookDeliveryController::class, 'replay'])->name('webhook-deliveries.replay');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
