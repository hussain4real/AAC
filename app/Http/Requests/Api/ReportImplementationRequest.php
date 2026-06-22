<?php

namespace App\Http\Requests\Api;

use App\Enums\SdkLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an SDK implementation-status report. The caller is authenticated by
 * the `sdk.auth` middleware, so authorization always passes here.
 */
class ReportImplementationRequest extends FormRequest
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
            'implementations' => ['required', 'array', 'min:1'],
            'implementations.*.tool' => ['required', 'string', 'max:255'],
            'implementations.*.handler_name' => ['required', 'string', 'max:255'],
            'implementations.*.version' => ['required', 'string', 'max:32'],
            'implementations.*.schema_fingerprint' => ['nullable', 'string', 'max:128'],
            'implementations.*.language' => ['nullable', Rule::enum(SdkLanguage::class)],
            'implementations.*.sdk_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
