<?php

namespace App\Http\Requests\Maacc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
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
            'llm_provider_id' => ['sometimes', 'required', 'string', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)->where('status', 'approved')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'required', 'string'],
            'temperature' => ['sometimes', 'required', 'numeric', 'between:0,2'],
            'max_tokens' => ['sometimes', 'required', 'integer', 'min:1', 'max:200000'],
            'description' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'tool_ids' => ['sometimes', 'array'],
            'tool_ids.*' => ['string', Rule::exists('tool_contracts', 'id')->where('team_id', $teamId)->where('status', 'Active')],
        ];
    }
}
