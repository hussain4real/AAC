<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Environment;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'application_id' => ['required', 'string', Rule::exists('applications', 'id')->where('team_id', $teamId)->where('status', 'active')],
            'name' => ['required', 'string', 'max:255'],
            'environment' => ['required', Rule::enum(Environment::class)],
            'description' => ['nullable', 'string'],
            'business_owner' => ['nullable', 'string', 'max:255'],
            'technical_owner' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'llm_provider_ids' => ['sometimes', 'array'],
            'llm_provider_ids.*' => ['string', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)->where('status', 'approved')],
        ];
    }
}
