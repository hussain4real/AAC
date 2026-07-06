<?php

namespace App\Actions\Maacc;

use App\Enums\LlmStatus;
use App\Enums\VaultSecretKind;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Support\ProviderCatalog;
use App\Support\Secrets\Contracts\SecretVault;
use App\Support\Slug;

class CreateLlmProvider
{
    public function __construct(private readonly SecretVault $vault) {}

    /**
     * Add a model to the team's LLM catalog as an unpublished draft. The model
     * code is normalised to the bare id the provider expects, and an API key —
     * when supplied — is stored in the vault and bound to the model. The entry
     * must pass a live verification before it can be published.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): LlmProvider
    {
        $provider = (string) $data['provider'];
        $code = ProviderCatalog::normalizeCode($provider, (string) $data['code']);
        $apiKey = $data['api_key'] ?? null;

        unset($data['api_key'], $data['status']);

        $llmProvider = LlmProvider::create([
            ...$data,
            'code' => $code,
            'team_id' => $team->id,
            'slug' => Slug::unique('llm_providers', $code),
            'status' => LlmStatus::Draft->value,
        ]);

        if (is_string($apiKey) && $apiKey !== '') {
            $secret = $this->vault->store(
                $team,
                VaultSecretKind::LlmKey->reference($llmProvider->slug),
                $llmProvider->name.' key',
                VaultSecretKind::LlmKey,
                $apiKey,
            );

            $llmProvider->update(['vault_secret_id' => $secret->id]);
        }

        return $llmProvider;
    }
}
