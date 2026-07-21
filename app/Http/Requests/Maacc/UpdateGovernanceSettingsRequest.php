<?php

namespace App\Http\Requests\Maacc;

use App\Enums\PayloadHandling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGovernanceSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'retain_prompts_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retain_responses_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retain_tool_arguments_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retain_tool_results_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'audit_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'mask_sensitive_inputs' => ['required', 'boolean'],
            'mask_sensitive_outputs' => ['required', 'boolean'],
            'tool_result_handling' => ['required', Rule::enum(PayloadHandling::class)],
            'block_restricted_logging' => ['required', 'boolean'],
            'default_daily_run_quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
