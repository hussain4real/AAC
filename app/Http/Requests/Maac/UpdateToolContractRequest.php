<?php

namespace App\Http\Requests\Maac;

use App\Enums\ExecMode;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolContractRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['sometimes', 'required', Rule::enum(ToolScope::class)],
            'execution_mode' => ['sometimes', 'required', Rule::enum(ExecMode::class)],
            'sensitivity' => ['sometimes', 'required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['sometimes', 'required', 'integer', 'min:1', 'max:600'],
            'max_payload_kb' => ['sometimes', 'required', 'integer', 'min:1', 'max:10240'],
            'version' => ['sometimes', 'string', 'max:32'],
            'input_schema' => ['sometimes', 'required', 'array'],
            'input_schema.*' => ['required', 'string', 'max:64'],
            'output_schema' => ['sometimes', 'required', 'array'],
            'output_schema.*' => ['required', 'string', 'max:64'],
        ];
    }
}
