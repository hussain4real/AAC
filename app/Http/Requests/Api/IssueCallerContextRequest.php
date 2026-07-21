<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueCallerContextRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:@-]+$/'],
            'department' => ['nullable', 'string', Rule::in((array) config('maacc.caller_context.allowed_departments', []))],
            'roles' => ['sometimes', 'array', 'max:20'],
            'roles.*' => ['string', 'max:64', Rule::in((array) config('maacc.caller_context.allowed_roles', []))],
            'nonce' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'correlation_id' => ['nullable', 'string', 'max:128', 'regex:/^corr_[A-Za-z0-9._:-]+$/'],
            'expires_in' => ['sometimes', 'integer', 'min:30', 'max:300'],
        ];
    }
}
