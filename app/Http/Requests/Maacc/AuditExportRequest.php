<?php

namespace App\Http\Requests\Maacc;

use Illuminate\Foundation\Http\FormRequest;

class AuditExportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:json,csv'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'action' => ['nullable', 'string', 'max:128'],
            'actor' => ['nullable', 'integer'],
        ];
    }
}
