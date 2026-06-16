<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLlmProviderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:255'],
            'context_window' => ['required', 'string', 'max:32'],
            'input_cost' => ['required', 'numeric', 'min:0'],
            'output_cost' => ['required', 'numeric', 'min:0'],
            'sensitivity' => ['required', Rule::enum(Sensitivity::class)],
            'environments' => ['required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'status' => ['sometimes', Rule::enum(LlmStatus::class)],
            'note' => ['nullable', 'string'],
        ];
    }
}
