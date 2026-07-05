<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\VaultSecretKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\RotateVaultSecretRequest;
use App\Http\Requests\Maacc\StoreVaultSecretRequest;
use App\Models\LlmProvider;
use App\Models\VaultSecret;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Console management of the secrets vault: store new secret material, rotate it,
 * and forget it. The plaintext is written straight into the vault and never read
 * back to the console. Storing an LLM-key secret may bind it to an approved model
 * so the runtime resolves that model's API key from the vault.
 */
class VaultSecretController extends Controller
{
    /**
     * Store a new secret in the vault.
     */
    public function store(StoreVaultSecretRequest $request, SecretVault $vault): RedirectResponse
    {
        Gate::authorize('create', VaultSecret::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $kind = VaultSecretKind::from($request->validated('kind'));

        $provider = $this->boundProvider($request, $kind);
        $reference = $provider instanceof LlmProvider
            ? $kind->reference($provider->slug)
            : $kind->reference(Str::slug($request->validated('name')).'-'.Str::lower(Str::random(6)));

        $secret = $vault->store(
            $team,
            $reference,
            $request->validated('name'),
            $kind,
            $request->validated('value'),
            $request->user(),
        );

        if ($provider instanceof LlmProvider) {
            $provider->update(['vault_secret_id' => $secret->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Secret stored in the vault.']);

        return back();
    }

    /**
     * Rotate the given secret to new material.
     */
    public function rotate(RotateVaultSecretRequest $request, string $currentTeam, VaultSecret $vaultSecret, SecretVault $vault): RedirectResponse
    {
        Gate::authorize('update', $vaultSecret);

        $vault->rotate($vaultSecret, $request->validated('value'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Secret rotated (now version {$vaultSecret->version})."]);

        return back();
    }

    /**
     * Forget the given secret, unbinding any models that resolved their key from it.
     */
    public function destroy(Request $request, string $currentTeam, VaultSecret $vaultSecret, SecretVault $vault): RedirectResponse
    {
        Gate::authorize('delete', $vaultSecret);

        $vaultSecret->llmProviders()->update(['vault_secret_id' => null]);
        $vault->forget($vaultSecret);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Secret forgotten.']);

        return back();
    }

    /**
     * Resolve the approved model an LLM-key secret should bind to, if any.
     */
    private function boundProvider(StoreVaultSecretRequest $request, VaultSecretKind $kind): ?LlmProvider
    {
        $providerId = $request->validated('llm_provider_id');

        if (! $kind->bindsToModel() || $providerId === null) {
            return null;
        }

        return $request->user()->currentTeam()->firstOrFail()
            ->llmProviders()->whereKey($providerId)->first();
    }
}
