<?php

namespace App\Http\Requests\Maac;

use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
