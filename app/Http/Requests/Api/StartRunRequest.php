<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a runtime invocation request. The caller is authenticated by the
 * `sdk.auth` middleware, so authorization always passes here.
 */
class StartRunRequest extends FormRequest
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
            'input' => ['required', 'string', 'max:8000'],
            'caller' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The user prompt the agent should run against.
     */
    public function runInput(): string
    {
        return (string) $this->validated('input');
    }

    /**
     * An optional caller label recorded against the run.
     */
    public function caller(): ?string
    {
        $caller = $this->validated('caller');

        return is_string($caller) ? $caller : null;
    }
}
