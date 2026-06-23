<?php

namespace App\Http\Requests\Maac;

use App\Enums\ExecMode;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Http\Requests\Maac\Concerns\ValidatesToolConfig;
use App\Rules\ValidToolSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolContractRequest extends FormRequest
{
    use ValidatesToolConfig;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'application_id' => ['nullable', 'string', Rule::exists('applications', 'id')],
            'description' => ['nullable', 'string'],
            'scope' => ['required', Rule::enum(ToolScope::class)],
            'execution_mode' => ['required', Rule::enum(ExecMode::class)],
            'sensitivity' => ['required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:600'],
            'max_payload_kb' => ['required', 'integer', 'min:1', 'max:10240'],
            'version' => ['sometimes', 'string', 'max:32'],
            // JSON contract schemas: an object mapping field name => type string.
            'input_schema' => ['required', 'array', new ValidToolSchema],
            'input_schema.*' => ['required', 'string', 'max:64'],
            'output_schema' => ['required', 'array', new ValidToolSchema],
            'output_schema.*' => ['required', 'string', 'max:64'],
            ...$this->toolConfigRules($this->user()?->currentTeam()->value('id')),
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'input_schema.array' => 'The input schema must be a JSON object of field definitions.',
            'output_schema.array' => 'The output schema must be a JSON object of field definitions.',
        ];
    }
}
