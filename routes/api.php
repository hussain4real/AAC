<?php

use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Controllers\Api\V1\CallerContextController;
use App\Http\Controllers\Api\V1\ManifestController;
use App\Http\Controllers\Api\V1\RunStreamController;
use App\Http\Controllers\Api\V1\SdkVersionController;
use App\Http\Controllers\Api\V1\ToolImplementationController;
use App\Http\Controllers\Api\V1\ToolResultController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use App\Http\Middleware\AddApiVersionHeader;
use App\Http\Middleware\EnforceApiEnvelopeLimits;
use App\Http\Middleware\LimitConcurrentSdkRequests;
use App\Http\Middleware\RejectDuplicateJsonKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

/*
|--------------------------------------------------------------------------
| SDK / Runtime API (v1)
|--------------------------------------------------------------------------
|
| Authenticated with Passport client_credentials tokens issued to a registered
| application's credential (exchanged at /oauth/token). EnsureClientIsResourceOwner
| validates the token; sdk.auth resolves the credential → application context.
|
*/
Route::prefix('v1')
    ->middleware([EnforceApiEnvelopeLimits::class, AddApiVersionHeader::class, RejectDuplicateJsonKeys::class, EnsureClientIsResourceOwner::class, 'sdk.auth', LimitConcurrentSdkRequests::class, 'throttle:maacc-sdk'])
    ->name('api.v1.')
    ->group(function () {
        // SDK version negotiation — API contract version, supported client
        // window, packages, deprecations, and the caller's compatibility.
        Route::get('sdk', [SdkVersionController::class, 'show'])->name('sdk');

        // SDK manifest sync — fetch available agents + required client tools.
        Route::get('manifest', [ManifestController::class, 'show'])->name('manifest');
        Route::post('caller-contexts', [CallerContextController::class, 'store'])->name('caller-contexts.store');

        // SDK implementation status reporting.
        Route::post('tool-implementations', [ToolImplementationController::class, 'store'])
            ->name('tool-implementations.store');

        // Runtime — invoke a published agent (sync or async) and read run status.
        Route::post('agents/{agentSlug}/runs', [AgentRunController::class, 'store'])->name('runs.store');
        Route::get('runs/{runId}', [AgentRunController::class, 'show'])->name('runs.show');

        // Runtime — stream a run's lifecycle as Server-Sent Events.
        Route::get('runs/{runId}/stream', [RunStreamController::class, 'show'])->name('runs.stream');

        // Runtime — submit a client-side tool result for a paused run.
        Route::post('runs/{runId}/tool-results', [ToolResultController::class, 'store'])->name('runs.tool-results.store');

        // Webhooks — self-service registration of run-event delivery endpoints.
        Route::get('webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
        Route::post('webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
        Route::post('webhook-endpoints/{webhookEndpoint}/verify', [WebhookEndpointController::class, 'verify'])->name('webhook-endpoints.verify');
        Route::delete('webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
    });
