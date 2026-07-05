<?php

namespace App\Http\Requests\Api;

use App\Enums\WebhookEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a webhook endpoint registration from an authenticated application.
 * The caller is authenticated by the `sdk.auth` middleware; the endpoint is
 * always scoped to that application and its credential environment.
 */
class RegisterWebhookEndpointRequest extends FormRequest
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
            'url' => ['required', 'url:http,https', 'max:2048'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string', Rule::in([...WebhookEventType::values(), '*'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The destination URL MAACC will post run events to.
     */
    public function webhookUrl(): string
    {
        return (string) $this->validated('url');
    }

    /**
     * The event types the endpoint subscribes to, defaulting to all events.
     *
     * @return array<int, string>
     */
    public function events(): array
    {
        $events = $this->validated('events');

        if (! is_array($events) || $events === []) {
            return ['*'];
        }

        return array_values(array_unique(array_map('strval', $events)));
    }

    /**
     * An optional human-readable description for the endpoint.
     */
    public function description(): ?string
    {
        $description = $this->validated('description');

        return is_string($description) && $description !== '' ? $description : null;
    }
}
