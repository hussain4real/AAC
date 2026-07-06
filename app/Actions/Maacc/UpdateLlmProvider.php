<?php

namespace App\Actions\Maacc;

use App\Enums\LlmStatus;
use App\Enums\VaultSecretKind;
use App\Models\LlmProvider;
use App\Support\ProviderCatalog;
use App\Support\Secrets\Contracts\SecretVault;

class UpdateLlmProvider
{
    public function __construct(private readonly SecretVault $vault) {}

    /**
     * Update a model in the LLM catalog. The code is re-normalised, a supplied
     * API key is rotated into the vault, and any change to the connection
     * details (code, provider, or key) clears the verification and drops an
     * approved model back to draft — so a re-pointed model must be verified and
     * published again before it can serve traffic.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(LlmProvider $llmProvider, array $data): LlmProvider
    {
        $apiKey = $data['api_key'] ?? null;
        unset($data['api_key']);

        if (array_key_exists('code', $data) || array_key_exists('provider', $data)) {
            $provider = (string) ($data['provider'] ?? $llmProvider->provider);
            $data['code'] = ProviderCatalog::normalizeCode($provider, (string) ($data['code'] ?? $llmProvider->code));
        }

        $connectionChanged = $this->connectionChanged($llmProvider, $data);

        $llmProvider->update($data);

        if (is_string($apiKey) && $apiKey !== '') {
            $reference = VaultSecretKind::LlmKey->reference($llmProvider->slug);

            // The model dialog and the Secrets Vault page manage the same vault
            // secret (`llm_key:{slug}`). Preserve a name the operator may have set
            // for it on the vault page instead of clobbering it with the default.
            $name = $llmProvider->team->vaultSecrets()->where('reference', $reference)->value('name')
                ?? $llmProvider->name.' key';

            $secret = $this->vault->store(
                $llmProvider->team,
                $reference,
                $name,
                VaultSecretKind::LlmKey,
                $apiKey,
            );

            $llmProvider->update(['vault_secret_id' => $secret->id]);
            $connectionChanged = true;
        }

        if ($connectionChanged) {
            $this->resetVerification($llmProvider);
        }

        return $llmProvider;
    }

    /**
     * Whether the update changes the model code or provider away from their
     * current values.
     *
     * @param  array<string, mixed>  $data
     */
    private function connectionChanged(LlmProvider $llmProvider, array $data): bool
    {
        return (array_key_exists('code', $data) && $data['code'] !== $llmProvider->code)
            || (array_key_exists('provider', $data) && $data['provider'] !== $llmProvider->provider);
    }

    /**
     * Clear the verification state and unpublish an approved model, forcing it
     * to be re-verified before it can go live again.
     */
    private function resetVerification(LlmProvider $llmProvider): void
    {
        $llmProvider->forceFill([
            'verification_status' => null,
            'verification_message' => null,
            'verified_at' => null,
            'verification_checked_at' => null,
            'status' => $llmProvider->status === LlmStatus::Approved ? LlmStatus::Draft : $llmProvider->status,
        ])->save();
    }
}
