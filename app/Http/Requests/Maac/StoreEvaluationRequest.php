<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()->value('id');
        $agentIds = Agent::query()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $teamId))
            ->pluck('id')
            ->all();

        return [
            'evaluation_dataset_id' => ['required', 'uuid', Rule::exists('evaluation_datasets', 'id')->where('team_id', $teamId)],
            'agent_id' => ['required', 'uuid', Rule::in($agentIds)],
            'environment' => ['required', Rule::enum(Environment::class)],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }
}
