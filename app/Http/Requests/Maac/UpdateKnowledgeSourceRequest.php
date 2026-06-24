<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeSourceRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'application_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('applications', 'id')->where('team_id', $teamId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sensitivity' => ['sometimes', 'required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'environments' => ['sometimes', 'required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'status' => ['sometimes', Rule::enum(KnowledgeSourceStatus::class)],
        ];
    }
}
