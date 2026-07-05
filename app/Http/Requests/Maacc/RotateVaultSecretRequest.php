<?php

namespace App\Http\Requests\Maacc;

use Illuminate\Foundation\Http\FormRequest;

class RotateVaultSecretRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:8192'],
        ];
    }
}
