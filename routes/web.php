<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Maac\AgentController;
use App\Http\Controllers\Maac\ApplicationController;
use App\Http\Controllers\Maac\ApprovalRequestController;
use App\Http\Controllers\Maac\AuditExportController;
use App\Http\Controllers\Maac\ConsoleController;
use App\Http\Controllers\Maac\CredentialController;
use App\Http\Controllers\Maac\DataSourceController;
use App\Http\Controllers\Maac\EvaluationCaseController;
use App\Http\Controllers\Maac\EvaluationController;
use App\Http\Controllers\Maac\EvaluationDatasetController;
use App\Http\Controllers\Maac\GovernanceSettingController;
use App\Http\Controllers\Maac\IncidentController;
use App\Http\Controllers\Maac\KnowledgeDocumentController;
use App\Http\Controllers\Maac\KnowledgeSourceController;
use App\Http\Controllers\Maac\LlmProviderController;
use App\Http\Controllers\Maac\McpConnectorController;
use App\Http\Controllers\Maac\ModelRoutingPolicyController;
use App\Http\Controllers\Maac\PlaygroundRunController;
use App\Http\Controllers\Maac\ProjectController;
use App\Http\Controllers\Maac\QuotaLimitController;
use App\Http\Controllers\Maac\SsoConnectionController;
use App\Http\Controllers\Maac\ToolContractController;
use App\Http\Controllers\Maac\VaultSecretController;
use App\Http\Controllers\Maac\VersionJourneyExportController;
use App\Http\Controllers\Maac\WebhookDeliveryController;
use App\Http\Controllers\Maac\WebhookEndpointController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Enterprise SSO login (guest-accessible auth entry points).
Route::get('sso/{ssoConnection}/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('sso/{ssoConnection}/callback', [SsoController::class, 'callback'])->name('sso.callback');

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
        Route::get('journey', [ConsoleController::class, 'journey'])->name('journey');
        Route::get('journey/export', [VersionJourneyExportController::class, 'download'])->name('journey-export');
        Route::get('playground', [ConsoleController::class, 'playground'])->name('playground');
        Route::get('runs', [ConsoleController::class, 'runs'])->name('runs');
        Route::get('runs/{run}', [ConsoleController::class, 'run'])->name('runs.show');
        Route::get('llm-providers', [ConsoleController::class, 'llmProviders'])->name('llm-providers');
        Route::get('connectors', [ConsoleController::class, 'connectors'])->name('connectors');
        Route::get('knowledge', [ConsoleController::class, 'knowledge'])->name('knowledge');
        Route::get('data-sources', [ConsoleController::class, 'dataSources'])->name('data-sources');
        Route::get('evaluations', [ConsoleController::class, 'evaluations'])->name('evaluations');
        Route::get('governance', [ConsoleController::class, 'governance'])->name('governance');
        Route::get('webhooks', [ConsoleController::class, 'webhooks'])->name('webhooks');
        Route::get('vault', [ConsoleController::class, 'vault'])->name('vault');
        Route::get('routing', [ConsoleController::class, 'routing'])->name('routing');
        Route::get('identity', [ConsoleController::class, 'identity'])->name('identity');
        Route::get('incidents', [ConsoleController::class, 'incidents'])->name('incidents');
        Route::get('platform-settings', [ConsoleController::class, 'settings'])->name('platform-settings');

        // MAAC console (Phase 2 — database-backed writes)
        Route::post('applications/{application}/credentials', [CredentialController::class, 'store'])->name('applications.credentials.store');
        Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
        Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');

        Route::post('agents/{agent}/publish', [AgentController::class, 'publish'])->name('agents.publish');

        // MAAC console (Phase 7+ — real playground runtime: invoke a published
        // agent from the console via the same AgentRunner the SDK uses).
        Route::post('playground/agents/{agent}/runs', [PlaygroundRunController::class, 'store'])->name('playground.runs.store');
        Route::post('playground/runs/{run}/tool-result', [PlaygroundRunController::class, 'toolResult'])->name('playground.runs.tool-result');

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

        // MAAC console (Phase 6F — knowledge retrieval/RAG sources)
        Route::resource('knowledge-sources', KnowledgeSourceController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['knowledge-sources' => 'knowledgeSource']);
        Route::post('knowledge-sources/{knowledgeSource}/reindex', [KnowledgeSourceController::class, 'reindex'])->name('knowledge-sources.reindex');
        Route::post('knowledge-sources/{knowledgeSource}/documents', [KnowledgeDocumentController::class, 'store'])->name('knowledge-sources.documents.store');
        Route::delete('knowledge-documents/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->name('knowledge-documents.destroy');

        // MAAC console (Phase 8A — governed read-only database data sources)
        Route::resource('data-sources', DataSourceController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['data-sources' => 'dataSource']);
        Route::post('data-sources/{dataSource}/refresh', [DataSourceController::class, 'refresh'])->name('data-sources.refresh');

        // MAAC console (Phase 6F — evaluation lab)
        Route::resource('evaluation-datasets', EvaluationDatasetController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['evaluation-datasets' => 'evaluationDataset']);
        Route::resource('evaluation-cases', EvaluationCaseController::class)
            ->only(['store', 'destroy'])
            ->parameters(['evaluation-cases' => 'evaluationCase']);
        Route::resource('evaluations', EvaluationController::class)
            ->only(['store', 'destroy'])
            ->parameters(['evaluations' => 'evaluation']);

        // MAAC console (Phase 5 — governance & security hardening)
        Route::post('approvals', [ApprovalRequestController::class, 'store'])->name('approvals.store');
        Route::post('approvals/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->name('approvals.reject');
        Route::put('governance-settings', [GovernanceSettingController::class, 'update'])->name('governance-settings.update');
        Route::get('audit-export', [AuditExportController::class, 'download'])->name('audit-export');
        Route::resource('quotas', QuotaLimitController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['quotas' => 'quotaLimit']);

        // MAAC console (Phase 6D — webhook endpoints & delivery observability)
        Route::resource('webhooks', WebhookEndpointController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['webhooks' => 'webhookEndpoint']);
        Route::post('webhooks/{webhookEndpoint}/rotate', [WebhookEndpointController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('webhook-deliveries/{webhookDelivery}/replay', [WebhookDeliveryController::class, 'replay'])->name('webhook-deliveries.replay');

        // MAAC console (Phase 6G — enterprise identity, secrets & advanced governance)
        Route::resource('vault-secrets', VaultSecretController::class)
            ->only(['store', 'destroy'])
            ->parameters(['vault-secrets' => 'vaultSecret']);
        Route::post('vault-secrets/{vaultSecret}/rotate', [VaultSecretController::class, 'rotate'])->name('vault-secrets.rotate');

        Route::resource('routing-policies', ModelRoutingPolicyController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['routing-policies' => 'modelRoutingPolicy']);

        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');

        Route::resource('sso-connections', SsoConnectionController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['sso-connections' => 'ssoConnection']);
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
