<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Environment;
use App\Enums\QuotaScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotaLimitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::enum(QuotaScope::class)],
            // A non-platform scope matches by subject UUID, so when one is
            // submitted a subject must come with it (see QuotaGuard::matches).
            'subject_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => QuotaScope::tryFrom((string) $this->input('scope'))?->requiresSubject() ?? false),
                'string',
                'max:255',
            ],
            'environment' => ['nullable', Rule::enum(Environment::class)],
            'max_runs_per_day' => ['nullable', 'integer', 'min:1'],
            'max_tokens_per_day' => ['nullable', 'integer', 'min:1'],
            'enabled' => ['sometimes', 'boolean'],
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
            'subject_id.required' => 'Select a subject for non-platform quota scopes.',
        ];
    }
}
