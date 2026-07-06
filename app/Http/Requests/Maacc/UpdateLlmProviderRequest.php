<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\Sensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLlmProviderRequest extends FormRequest
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
            'code' => ['sometimes', 'required', 'string', 'max:255'],
            'provider' => ['sometimes', 'required', 'string', 'max:255'],
            'context_window' => ['sometimes', 'required', 'string', 'max:32'],
            'input_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'output_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sensitivity' => ['sometimes', 'required', Rule::enum(Sensitivity::class)],
            'environments' => ['sometimes', 'required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            // `approved` is intentionally excluded — a model reaches the live
            // catalog only through the verification-gated publish action.
            'status' => ['sometimes', 'required', Rule::in([
                LlmStatus::Draft->value,
                LlmStatus::Deprecated->value,
                LlmStatus::Blocked->value,
            ])],
            'api_key' => ['nullable', 'string', 'max:512'],
            'note' => ['nullable', 'string'],
        ];
    }
}
