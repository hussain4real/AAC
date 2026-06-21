<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use App\Enums\QuotaScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotaLimitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(QuotaScope::class)],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', Rule::enum(Environment::class)],
            'max_runs_per_day' => ['nullable', 'required_without:max_tokens_per_day', 'integer', 'min:1'],
            'max_tokens_per_day' => ['nullable', 'required_without:max_runs_per_day', 'integer', 'min:1'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
