<?php

namespace App\Http\Requests\Maac;

use App\Enums\AgentStatus;
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
        return [
            'llm_provider_id' => ['sometimes', 'required', 'string', Rule::exists('llm_providers', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'required', 'string'],
            'temperature' => ['sometimes', 'required', 'numeric', 'between:0,2'],
            'max_tokens' => ['sometimes', 'required', 'integer', 'min:1', 'max:200000'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(AgentStatus::class)],
            'tool_ids' => ['sometimes', 'array'],
            'tool_ids.*' => ['string', Rule::exists('tool_contracts', 'id')],
        ];
    }
}
