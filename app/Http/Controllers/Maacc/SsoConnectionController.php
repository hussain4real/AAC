<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\SsoConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreSsoConnectionRequest;
use App\Http\Requests\Maacc\UpdateSsoConnectionRequest;
use App\Models\SsoConnection;
use App\Support\Governance\AuditLedger;
use App\Support\Sso\OidcTokenValidator;
use App\Support\Sso\SsoException;
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
            'status' => SsoConnectionStatus::Draft,
            'tested_at' => null,
            'approved_at' => null,
            'approved_by' => null,
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

        $ssoConnection->update([
            ...$data,
            'status' => SsoConnectionStatus::Draft,
            'tested_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection updated.']);

        return back();
    }

    /**
     * Verify the pinned signing-key endpoint before approval can be requested.
     */
    public function test(Request $request, string $currentTeam, SsoConnection $ssoConnection, OidcTokenValidator $validator): RedirectResponse
    {
        Gate::authorize('test', $ssoConnection);

        try {
            $validator->testKeySet($ssoConnection);
        } catch (SsoException $exception) {
            $this->auditLifecycle($request, $ssoConnection, 'sso.connection_test_failed', ['reason' => $exception->getMessage()]);

            return back()->withErrors(['sso' => 'Connection test failed: '.$exception->getMessage().'.']);
        }

        $ssoConnection->forceFill([
            'status' => SsoConnectionStatus::PendingApproval,
            'tested_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        $this->auditLifecycle($request, $ssoConnection, 'sso.connection_tested');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Connection tested and submitted for independent approval.']);

        return back();
    }

    /**
     * Independently approve a successfully tested connection for activation.
     */
    public function approve(Request $request, string $currentTeam, SsoConnection $ssoConnection): RedirectResponse
    {
        Gate::authorize('approve', $ssoConnection);

        abort_if($ssoConnection->created_by === $request->user()?->getAuthIdentifier(), 422, 'The connection creator cannot approve their own connection.');
        abort_unless($ssoConnection->status === SsoConnectionStatus::PendingApproval && $ssoConnection->tested_at !== null, 422, 'The connection must pass testing before approval.');

        $ssoConnection->forceFill([
            'status' => SsoConnectionStatus::Active,
            'approved_at' => now(),
            'approved_by' => $request->user()?->getAuthIdentifier(),
        ])->save();

        $this->auditLifecycle($request, $ssoConnection, 'sso.connection_approved');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection approved and activated.']);

        return back();
    }

    /**
     * Immediately disable new SSO logins without deleting audit evidence.
     */
    public function disable(Request $request, string $currentTeam, SsoConnection $ssoConnection): RedirectResponse
    {
        Gate::authorize('update', $ssoConnection);

        $ssoConnection->forceFill(['status' => SsoConnectionStatus::Disabled])->save();
        $this->auditLifecycle($request, $ssoConnection, 'sso.connection_disabled');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identity connection disabled.']);

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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function auditLifecycle(Request $request, SsoConnection $connection, string $action, array $metadata = []): void
    {
        app(AuditLedger::class)->record([
            'team_id' => $connection->team_id,
            'actor_user_id' => $request->user()->getAuthIdentifier(),
            'actor_label' => $request->user()->name,
            'action' => $action,
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'metadata' => ['connection' => $connection->slug, ...$metadata],
            'ip_address' => $request->ip(),
        ]);
    }
}
