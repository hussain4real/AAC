<?php

namespace App\Http\Requests\Maac;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluationDatasetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()->value('id');
        $appIds = Application::query()->where('team_id', $teamId)->pluck('id')->all();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'project_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('projects', 'id')->whereIn('application_id', $appIds)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
