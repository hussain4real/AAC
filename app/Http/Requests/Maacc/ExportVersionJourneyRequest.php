<?php

namespace App\Http\Requests\Maacc;

use Illuminate\Foundation\Http\FormRequest;

class ExportVersionJourneyRequest extends FormRequest
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
        ];
    }
}
