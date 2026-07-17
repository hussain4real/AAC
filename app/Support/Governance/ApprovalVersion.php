<?php

namespace App\Support\Governance;

use App\Models\Credential;
use App\Models\LlmProvider;

class ApprovalVersion
{
    /**
     * Fingerprint every model property covered by a promotion decision.
     */
    public function model(LlmProvider $model): string
    {
        return hash('sha256', (string) json_encode([
            'id' => $model->id,
            'team_id' => $model->team_id,
            'code' => $model->code,
            'provider' => $model->provider,
            'status' => $model->status->value,
            'sensitivity' => $model->sensitivity->value,
            'environments' => $model->environments,
            'vault_secret_id' => $model->vault_secret_id,
            'platform_owned' => $model->platform_owned,
            'verification_status' => $model->verification_status?->value,
            'verified_at' => $model->verified_at?->toJSON(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Fingerprint the live credential state a proposed change was based on.
     */
    public function credential(Credential $credential): string
    {
        return hash('sha256', (string) json_encode([
            'id' => $credential->id,
            'application_id' => $credential->application_id,
            'environment' => $credential->environment->value,
            'oauth_client_id' => $credential->oauth_client_id,
            'secret_hash' => $credential->secret_hash,
            'status' => $credential->status->value,
            'rotated_at' => $credential->rotated_at?->toJSON(),
            'revoked_at' => $credential->revoked_at?->toJSON(),
        ], JSON_THROW_ON_ERROR));
    }
}
