<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Enums\ToolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolAssignmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tool_contract_id' => ['required', 'string', Rule::exists('tool_contracts', 'id')],
            'scope' => ['required', Rule::enum(ToolScope::class)],
            'project_id' => ['nullable', 'required_if:scope,project', 'string', Rule::exists('projects', 'id')],
            'agent_id' => ['nullable', 'required_if:scope,agent', 'string', Rule::exists('agents', 'id')],
            'environment' => ['nullable', Rule::enum(Environment::class)],
        ];
    }
}
