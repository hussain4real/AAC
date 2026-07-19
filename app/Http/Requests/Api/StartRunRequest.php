<?php

namespace App\Http\Requests\Api;

use App\Enums\RunMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

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
            'caller_context' => ['nullable', 'string', 'max:4096'],
            'mode' => ['nullable', Rule::enum(RunMode::class)],
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

    public function callerContextEnvelope(): ?string
    {
        $context = $this->validated('caller_context');

        return is_string($context) ? $context : null;
    }

    /**
     * The invocation mode, defaulting to a synchronous (request-blocking) run.
     */
    public function mode(): RunMode
    {
        $mode = $this->validated('mode');

        return is_string($mode) ? RunMode::from($mode) : RunMode::Sync;
    }

    /**
     * Optional application-scoped retry key. Official v1 SDKs send one for each
     * logical invocation and reuse it only when retrying that invocation.
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return is_string($key) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) === 1 ? $key : null;
    }

    /**
     * Stable hash used to distinguish a replay from key reuse with new input.
     */
    public function requestHash(string $agentSlug): string
    {
        $payload = Arr::sortRecursive([
            'agent' => $agentSlug,
            'input' => $this->runInput(),
            'caller' => $this->caller(),
            'caller_context' => $this->callerContextEnvelope(),
            'mode' => $this->mode()->value,
        ]);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
