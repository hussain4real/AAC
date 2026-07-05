<?php

namespace App\Http\Requests\Maacc;

use App\Enums\RoutingStrategy;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModelRoutingPolicyRequest extends FormRequest
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
            'agent_id' => [
                'required',
                'uuid',
                Rule::exists('agents', 'id')->whereIn('project_id', $this->teamProjectIds($teamId)),
                Rule::unique('model_routing_policies', 'agent_id'),
            ],
            'strategy' => ['required', Rule::enum(RoutingStrategy::class)],
            'primary_provider_id' => ['nullable', 'uuid', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)],
            'fallback_provider_ids' => ['nullable', 'array', 'max:8'],
            'fallback_provider_ids.*' => ['uuid', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)],
            'max_cost_per_1k' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'max_latency_ms' => ['nullable', 'integer', 'min:1', 'max:600000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The ids of the projects owned by the team, used to scope the agent rule.
     *
     * @return array<int, string>
     */
    private function teamProjectIds(?string $teamId): array
    {
        return Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $teamId))
            ->pluck('id')
            ->all();
    }
}
