<?php

use App\Http\Controllers\Api\V1\ManifestController;
use App\Http\Controllers\Api\V1\ToolImplementationController;
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
    ->middleware([EnsureClientIsResourceOwner::class, 'sdk.auth'])
    ->name('api.v1.')
    ->group(function () {
        // SDK manifest sync — fetch available agents + required client tools.
        Route::get('manifest', [ManifestController::class, 'show'])->name('manifest');

        // SDK implementation status reporting.
        Route::post('tool-implementations', [ToolImplementationController::class, 'store'])
            ->name('tool-implementations.store');
    });
