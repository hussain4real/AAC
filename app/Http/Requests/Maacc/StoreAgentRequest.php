<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Sensitivity;
use App\Models\Project;
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
        $teamId = $this->user()?->currentTeam()->value('id');
        $projectIds = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $teamId))
            ->pluck('id')
            ->all();

        return [
            'project_id' => ['required', 'string', Rule::exists('projects', 'id')->whereIn('id', $projectIds)],
            'llm_provider_id' => ['required', 'string', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)->where('status', 'approved')],
            'name' => ['required', 'string', 'max:255'],
            'agent_slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('agents', 'agent_slug')],
            'system_prompt' => ['required', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'description' => ['nullable', 'string'],
            'sensitivity' => ['sometimes', Rule::enum(Sensitivity::class)],
            'requires_runtime_approval' => ['sometimes', 'boolean'],
            'status' => ['prohibited'],
            'tool_ids' => ['sometimes', 'array'],
            'tool_ids.*' => ['string', Rule::exists('tool_contracts', 'id')->where('team_id', $teamId)->where('status', 'Active')],
        ];
    }
}
