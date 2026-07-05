<?php

namespace App\Http\Requests\Maacc;

use App\Enums\RoutingStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModelRoutingPolicyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The bound agent is not
     * editable — a policy stays attached to the agent it routes.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()->value('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'strategy' => ['sometimes', Rule::enum(RoutingStrategy::class)],
            'primary_provider_id' => ['nullable', 'uuid', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)],
            'fallback_provider_ids' => ['nullable', 'array', 'max:8'],
            'fallback_provider_ids.*' => ['uuid', Rule::exists('llm_providers', 'id')->where('team_id', $teamId)],
            'max_cost_per_1k' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'max_latency_ms' => ['nullable', 'integer', 'min:1', 'max:600000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
