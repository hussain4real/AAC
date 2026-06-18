<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a client-side tool result submission for a paused run. The caller is
 * authenticated by the `sdk.auth` middleware, so authorization always passes
 * here; the run/tool-call ownership and schema are enforced by the runtime.
 */
class SubmitToolResultRequest extends FormRequest
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
            'tool_call_id' => ['required', 'string', 'max:255'],
            'result' => ['required', 'array'],
        ];
    }

    /**
     * The identifier of the tool call the result is for.
     */
    public function toolCallId(): string
    {
        return (string) $this->validated('tool_call_id');
    }

    /**
     * The tool result payload submitted by the application.
     *
     * @return array<string, mixed>
     */
    public function result(): array
    {
        $result = $this->validated('result');

        return is_array($result) ? $result : [];
    }
}
