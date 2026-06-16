<?php

namespace App\Http\Requests\Maac;

use App\Enums\AppStatus;
use App\Enums\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', Rule::unique('applications', 'code')],
            'department' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'environment' => ['required', Rule::enum(Environment::class)],
            'status' => ['sometimes', Rule::enum(AppStatus::class)],
            'stack' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }
}
