<?php

use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Controllers\Api\V1\ManifestController;
use App\Http\Controllers\Api\V1\SdkVersionController;
use App\Http\Controllers\Api\V1\ToolImplementationController;
use App\Http\Controllers\Api\V1\ToolResultController;
use App\Http\Middleware\AddApiVersionHeader;
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
    ->middleware([AddApiVersionHeader::class, EnsureClientIsResourceOwner::class, 'sdk.auth'])
    ->name('api.v1.')
    ->group(function () {
        // SDK version negotiation — API contract version, supported client
        // window, packages, deprecations, and the caller's compatibility.
        Route::get('sdk', [SdkVersionController::class, 'show'])->name('sdk');

        // SDK manifest sync — fetch available agents + required client tools.
        Route::get('manifest', [ManifestController::class, 'show'])->name('manifest');

        // SDK implementation status reporting.
        Route::post('tool-implementations', [ToolImplementationController::class, 'store'])
            ->name('tool-implementations.store');

        // Runtime — invoke a published agent and read run status.
        Route::post('agents/{agentSlug}/runs', [AgentRunController::class, 'store'])->name('runs.store');
        Route::get('runs/{runId}', [AgentRunController::class, 'show'])->name('runs.show');

        // Runtime — submit a client-side tool result for a paused run.
        Route::post('runs/{runId}/tool-results', [ToolResultController::class, 'store'])->name('runs.tool-results.store');
    });
