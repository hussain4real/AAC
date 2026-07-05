<?php

namespace App\Http\Controllers\Maacc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreSsoConnectionRequest;
use App\Http\Requests\Maacc\UpdateSsoConnectionRequest;
use App\Models\SsoConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of enterprise identity (SSO) connections: register a
 * provider, edit its endpoints / claim mapping / group→role rules, and remove it.
 * The OAuth client secret is stored encrypted and never returned to the console.
 */
class SsoConnectionController extends Controller
{
    /**
     * Register a new SSO connection.
     */
    public function store(StoreSsoConnectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', SsoConnection::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        $connection = new SsoConnection([
            ...$request->validated(),
            'team_id' => $team->id,
            'slug' => SsoConnection::uniqueSlug($request->validated('name')),
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $connection->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection registered.']);

        return back();
    }

    /**
     * Update the given connection, preserving the secret when not re-entered.
     */
    public function update(UpdateSsoConnectionRequest $request, string $currentTeam, SsoConnection $ssoConnection): RedirectResponse
    {
        Gate::authorize('update', $ssoConnection);

        $data = $request->validated();

        if (! Arr::hasAny($data, ['client_secret']) || blank($data['client_secret'] ?? null)) {
            unset($data['client_secret']);
        }

        $ssoConnection->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection updated.']);

        return back();
    }

    /**
     * Delete the given connection.
     */
    public function destroy(Request $request, string $currentTeam, SsoConnection $ssoConnection): RedirectResponse
    {
        Gate::authorize('delete', $ssoConnection);

        $ssoConnection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection removed.']);

        return back();
    }
}
