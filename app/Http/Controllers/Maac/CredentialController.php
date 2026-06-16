<?php

namespace App\Http\Controllers\Maac;

use App\Concerns\RecordsMaacAudit;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreCredentialRequest;
use App\Models\Application;
use App\Models\Credential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CredentialController extends Controller
{
    use RecordsMaacAudit;

    /**
     * Generate a new credential for an application environment. The plaintext
     * secret is flashed once and never stored.
     */
    public function store(StoreCredentialRequest $request, string $currentTeam, Application $application): RedirectResponse
    {
        Gate::authorize('create', [Credential::class, $application]);

        $environment = Environment::from($request->string('environment')->value());
        $plainSecret = Credential::generateSecret();

        $credential = new Credential([
            'application_id' => $application->id,
            'environment' => $environment->value,
            'label' => $request->string('label')->value() ?: $environment->label().' credentials',
            'client_id' => Credential::generateClientId(),
            'status' => CredentialStatus::Active->value,
            'created_by' => $request->user()->id,
        ]);
        $credential->fillSecret($plainSecret);
        $credential->save();

        $this->recordAudit($request, 'credential.created', $credential, ['environment' => $environment->value]);

        $this->flashSecret($credential, $plainSecret);

        return back();
    }

    /**
     * Rotate the credential's secret, preserving its identity and history.
     */
    public function rotate(Request $request, string $currentTeam, Credential $credential): RedirectResponse
    {
        Gate::authorize('rotate', $credential);

        $plainSecret = Credential::generateSecret();
        $credential->fillSecret($plainSecret);
        $credential->status = CredentialStatus::Active;
        $credential->rotated_at = Carbon::now();
        $credential->revoked_at = null;
        $credential->save();

        $this->recordAudit($request, 'credential.rotated', $credential);

        $this->flashSecret($credential, $plainSecret);

        return back();
    }

    /**
     * Revoke the credential so it can no longer authenticate.
     */
    public function revoke(Request $request, string $currentTeam, Credential $credential): RedirectResponse
    {
        Gate::authorize('revoke', $credential);

        $credential->update([
            'status' => CredentialStatus::Revoked->value,
            'revoked_at' => Carbon::now(),
        ]);

        $this->recordAudit($request, 'credential.revoked', $credential);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Credential revoked.']);

        return back();
    }

    /**
     * Flash the one-time plaintext secret for display.
     */
    private function flashSecret(Credential $credential, string $plainSecret): void
    {
        Inertia::flash('credentialSecret', [
            'clientId' => $credential->client_id,
            'secret' => $plainSecret,
        ]);
    }
}
