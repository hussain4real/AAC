<?php

namespace App\Http\Requests\Maacc;

use App\Enums\VaultSecretKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVaultSecretRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(VaultSecretKind::class)],
            'value' => ['required', 'string', 'max:8192'],
            'llm_provider_id' => [
                'nullable',
                'uuid',
                Rule::exists('llm_providers', 'id')->where('team_id', $teamId),
                Rule::requiredIf(fn (): bool => $this->input('kind') === VaultSecretKind::LlmKey->value),
            ],
        ];
    }
}
