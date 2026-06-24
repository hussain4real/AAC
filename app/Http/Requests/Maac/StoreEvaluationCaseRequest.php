<?php

namespace App\Http\Requests\Maac;

use App\Enums\EvaluationCaseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationCaseRequest extends FormRequest
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
            'evaluation_dataset_id' => ['required', 'uuid', Rule::exists('evaluation_datasets', 'id')->where('team_id', $teamId)],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(EvaluationCaseKind::class)],
            'input' => ['required', 'string', 'max:5000'],
            'expectations' => ['nullable', 'array'],
            'expectations.expected_contains' => ['nullable', 'array'],
            'expectations.expected_contains.*' => ['string', 'max:255'],
            'expectations.expected_tool' => ['nullable', 'string', 'max:128'],
            'expectations.forbidden_phrases' => ['nullable', 'array'],
            'expectations.forbidden_phrases.*' => ['string', 'max:255'],
            'expectations.expects_citation' => ['nullable', 'boolean'],
            'expectations.max_cost' => ['nullable', 'numeric', 'min:0'],
            'expectations.max_latency_ms' => ['nullable', 'integer', 'min:0'],
            'tool_stubs' => ['nullable', 'array'],
            'ordinal' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
