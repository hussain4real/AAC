<?php

namespace App\Http\Requests\Maac;

use App\Enums\ApprovalType;
use App\Enums\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestApprovalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ApprovalType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'environment' => ['nullable', Rule::enum(Environment::class)],
            'change' => ['nullable', 'string', 'max:255'],
        ];
    }
}
