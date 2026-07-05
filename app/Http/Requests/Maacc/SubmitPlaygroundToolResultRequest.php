<?php

namespace App\Http\Requests\Maacc;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a client-side tool result submitted from the console playground to
 * resume a paused run. Authorization is enforced in the controller via the
 * agent policy once the target run is resolved.
 */
class SubmitPlaygroundToolResultRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tool_call_id' => ['required', 'string'],
            'result' => ['required', 'array'],
        ];
    }

    /**
     * The id of the pending tool call the result is for.
     */
    public function toolCallId(): string
    {
        return (string) $this->validated('tool_call_id');
    }

    /**
     * The client-produced tool result payload.
     *
     * @return array<string, mixed>
     */
    public function result(): array
    {
        $result = $this->validated('result');

        return is_array($result) ? $result : [];
    }
}
