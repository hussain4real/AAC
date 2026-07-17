<?php

use App\Enums\PlatformPermission;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Maacc\AgentController;
use App\Http\Controllers\Maacc\ApplicationController;
use App\Http\Controllers\Maacc\ApprovalRequestController;
use App\Http\Controllers\Maacc\AuditExportController;
use App\Http\Controllers\Maacc\ConsoleController;
use App\Http\Controllers\Maacc\CredentialController;
use App\Http\Controllers\Maacc\DataSourceController;
use App\Http\Controllers\Maacc\EvaluationCaseController;
use App\Http\Controllers\Maacc\EvaluationController;
use App\Http\Controllers\Maacc\EvaluationDatasetController;
use App\Http\Controllers\Maacc\GovernanceSettingController;
use App\Http\Controllers\Maacc\IncidentController;
use App\Http\Controllers\Maacc\KnowledgeDocumentController;
use App\Http\Controllers\Maacc\KnowledgeSourceController;
use App\Http\Controllers\Maacc\LlmProviderController;
use App\Http\Controllers\Maacc\McpConnectorController;
use App\Http\Controllers\Maacc\ModelRoutingPolicyController;
use App\Http\Controllers\Maacc\PlatformAccessController;
use App\Http\Controllers\Maacc\PlaygroundRunController;
use App\Http\Controllers\Maacc\ProjectController;
use App\Http\Controllers\Maacc\QuotaLimitController;
use App\Http\Controllers\Maacc\SsoConnectionController;
use App\Http\Controllers\Maacc\ToolContractController;
use App\Http\Controllers\Maacc\VaultSecretController;
use App\Http\Controllers\Maacc\VersionJourneyExportController;
use App\Http\Controllers\Maacc\WebhookDeliveryController;
use App\Http\Controllers\Maacc\WebhookEndpointController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureCurrentTeamResourceOwnership;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Enterprise SSO login (guest-accessible auth entry points).
Route::get('sso/{ssoConnection}/redirect', [SsoController::class, 'redirect'])->middleware('throttle:sso')->name('sso.redirect');
Route::get('sso/{ssoConnection}/callback', [SsoController::class, 'callback'])->middleware('throttle:sso')->name('sso.callback');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureCurrentTeamResourceOwnership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // MAACC console (Phase 1 — mock-backed)
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
        Route::get('identity', [ConsoleController::class, 'identity'])
            ->middleware('permission:'.PlatformPermission::ViewIdentity->value)
            ->name('identity');
        Route::get('incidents', [ConsoleController::class, 'incidents'])->name('incidents');
        Route::get('platform-settings', [ConsoleController::class, 'settings'])->name('platform-settings');

        // MAACC console (Phase 2 — database-backed writes)
        Route::post('applications/{application}/credentials', [CredentialController::class, 'store'])->name('applications.credentials.store');
        Route::post('credentials/{credential}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
        Route::post('credentials/{credential}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');

        Route::post('agents/{agent}/publish', [AgentController::class, 'publish'])->name('agents.publish');

        // MAACC console (Phase 7+ — real playground runtime: invoke a published
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
        Route::post('llm-providers/{llmProvider}/verify', [LlmProviderController::class, 'verify'])->name('llm-providers.verify');
        Route::post('llm-providers/{llmProvider}/publish', [LlmProviderController::class, 'publish'])->name('llm-providers.publish');

        // MAACC console (Phase 6E — MCP connectors for connector-backed tools)
        Route::resource('connectors', McpConnectorController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['connectors' => 'mcpConnector']);
        Route::post('connectors/{mcpConnector}/discover', [McpConnectorController::class, 'discover'])->name('connectors.discover');

        // MAACC console (Phase 6F — knowledge retrieval/RAG sources)
        Route::resource('knowledge-sources', KnowledgeSourceController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['knowledge-sources' => 'knowledgeSource']);
        Route::post('knowledge-sources/{knowledgeSource}/reindex', [KnowledgeSourceController::class, 'reindex'])->name('knowledge-sources.reindex');
        Route::post('knowledge-sources/{knowledgeSource}/documents', [KnowledgeDocumentController::class, 'store'])->name('knowledge-sources.documents.store');
        Route::delete('knowledge-documents/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->name('knowledge-documents.destroy');

        // MAACC console (Phase 8A — governed read-only database data sources)
        Route::resource('data-sources', DataSourceController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['data-sources' => 'dataSource']);
        Route::post('data-sources/{dataSource}/refresh', [DataSourceController::class, 'refresh'])->name('data-sources.refresh');

        // MAACC console (Phase 6F — evaluation lab)
        Route::resource('evaluation-datasets', EvaluationDatasetController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['evaluation-datasets' => 'evaluationDataset']);
        Route::resource('evaluation-cases', EvaluationCaseController::class)
            ->only(['store', 'destroy'])
            ->parameters(['evaluation-cases' => 'evaluationCase']);
        Route::resource('evaluations', EvaluationController::class)
            ->only(['store', 'destroy'])
            ->parameters(['evaluations' => 'evaluation']);

        // MAACC console (Phase 5 — governance & security hardening)
        Route::post('approvals', [ApprovalRequestController::class, 'store'])->name('approvals.store');
        Route::post('approvals/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->name('approvals.reject');
        Route::put('governance-settings', [GovernanceSettingController::class, 'update'])->name('governance-settings.update');
        Route::get('audit-export', [AuditExportController::class, 'download'])->name('audit-export');
        Route::resource('quotas', QuotaLimitController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['quotas' => 'quotaLimit']);

        // MAACC console (Phase 6D — webhook endpoints & delivery observability)
        Route::resource('webhooks', WebhookEndpointController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['webhooks' => 'webhookEndpoint']);
        Route::post('webhooks/{webhookEndpoint}/rotate', [WebhookEndpointController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('webhook-deliveries/{webhookDelivery}/replay', [WebhookDeliveryController::class, 'replay'])->name('webhook-deliveries.replay');

        // MAACC console (Phase 6G — enterprise identity, secrets & advanced governance)
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
        Route::post('sso-connections/{ssoConnection}/test', [SsoConnectionController::class, 'test'])->name('sso-connections.test');
        Route::post('sso-connections/{ssoConnection}/approve', [SsoConnectionController::class, 'approve'])->name('sso-connections.approve');
        Route::post('sso-connections/{ssoConnection}/disable', [SsoConnectionController::class, 'disable'])->name('sso-connections.disable');

        // MAACC console (Phase 8B — platform administration RBAC). Gated by the
        // global platform permissions; a Super Admin passes via the Gate::before
        // override since the spatie middleware checks go through the gate.
        Route::get('access-control', [ConsoleController::class, 'accessControl'])
            ->name('access-control')
            ->middleware('permission:'.PlatformPermission::ViewUsers->value);
        Route::post('access-control/grants', [PlatformAccessController::class, 'store'])
            ->name('access-control.grants.store')
            ->middleware('permission:'.PlatformPermission::AssignRoles->value);
        Route::post('access-control/grants/{grant}/revoke', [PlatformAccessController::class, 'revoke'])
            ->name('access-control.grants.revoke')
            ->middleware('permission:'.PlatformPermission::AssignRoles->value);
        Route::post('access-control/grants/{grant}/certify', [PlatformAccessController::class, 'certify'])
            ->name('access-control.grants.certify')
            ->middleware('permission:'.PlatformPermission::ReviewAccess->value);
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
