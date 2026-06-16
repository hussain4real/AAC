<?php

namespace App\Http\Requests\Maac;

use App\Enums\AgentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'string', Rule::exists('projects', 'id')],
            'llm_provider_id' => ['required', 'string', Rule::exists('llm_providers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'agent_slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('agents', 'agent_slug')],
            'system_prompt' => ['required', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(AgentStatus::class)],
            'tool_ids' => ['sometimes', 'array'],
            'tool_ids.*' => ['string', Rule::exists('tool_contracts', 'id')],
        ];
    }
}
