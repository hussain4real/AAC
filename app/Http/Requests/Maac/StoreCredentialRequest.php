<?php

namespace App\Http\Requests\Maac;

use App\Enums\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCredentialRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'environment' => ['required', Rule::enum(Environment::class)],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
