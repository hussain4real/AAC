<?php

namespace App\Http\Requests\Maacc;

use App\Enums\IncidentActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(IncidentActionType::class)],
            'target' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
