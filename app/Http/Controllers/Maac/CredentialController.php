<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\CreateCredential;
use App\Actions\Maac\CredentialSecret;
use App\Actions\Maac\RevokeCredential;
use App\Actions\Maac\RotateCredential;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreCredentialRequest;
use App\Models\Application;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CredentialController extends Controller
{
    /**
     * Generate a new credential for an application environment. The plaintext
     * secret is flashed once and never stored.
     */
    public function store(StoreCredentialRequest $request, string $currentTeam, Application $application, CreateCredential $createCredential): RedirectResponse
    {
        Gate::authorize('create', [Credential::class, $application]);

        /** @var User $creator */
        $creator = $request->user();
        $this->flashSecret($createCredential->handle($application, $creator, $request->validated()));

        return back();
    }

    /**
     * Rotate the credential's secret, preserving its identity and history.
     */
    public function rotate(string $currentTeam, Credential $credential, RotateCredential $rotateCredential): RedirectResponse
    {
        Gate::authorize('rotate', $credential);

        $this->flashSecret($rotateCredential->handle($credential));

        return back();
    }

    /**
     * Revoke the credential so it can no longer authenticate.
     */
    public function revoke(string $currentTeam, Credential $credential, RevokeCredential $revokeCredential): RedirectResponse
    {
        Gate::authorize('revoke', $credential);

        $revokeCredential->handle($credential);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Credential revoked.']);

        return back();
    }

    /**
     * Flash the one-time plaintext secret for display.
     */
    private function flashSecret(CredentialSecret $credentialSecret): void
    {
        Inertia::flash('credentialSecret', [
            'clientId' => $credentialSecret->credential->client_id,
            'secret' => $credentialSecret->plainSecret,
        ]);
    }
}
