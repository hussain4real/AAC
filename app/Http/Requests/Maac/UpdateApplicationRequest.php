<?php

namespace App\Http\Requests\Maac;

use App\Enums\AppStatus;
use App\Enums\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
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
            'code' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('applications', 'code')->ignore($this->route('application'))],
            'department' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_email' => ['sometimes', 'required', 'email', 'max:255'],
            'environment' => ['sometimes', 'required', Rule::enum(Environment::class)],
            'status' => ['sometimes', 'required', Rule::enum(AppStatus::class)],
            'stack' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }
}
