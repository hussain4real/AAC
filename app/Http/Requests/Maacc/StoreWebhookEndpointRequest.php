<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Environment;
use App\Enums\WebhookEventType;
use App\Rules\SafeOutboundUrl;
use App\Support\Outbound\OutboundRequestPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a console webhook endpoint registration. The application must belong
 * to the acting user's current team; the controller authorizes via the policy.
 */
class StoreWebhookEndpointRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(OutboundRequestPolicy $policy): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'application_id' => ['required', 'uuid', Rule::exists('applications', 'id')->where('team_id', $teamId)],
            'environment' => ['required', Rule::enum(Environment::class)],
            'url' => ['required', new SafeOutboundUrl($policy, 'webhook'), 'max:2048'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string', Rule::in([...WebhookEventType::values(), '*'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The environment the endpoint receives events for.
     */
    public function environment(): Environment
    {
        return Environment::from((string) $this->validated('environment'));
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
     * The destination URL.
     */
    public function webhookUrl(): string
    {
        return (string) $this->validated('url');
    }

    /**
     * An optional human-readable description.
     */
    public function description(): ?string
    {
        $description = $this->validated('description');

        return is_string($description) && $description !== '' ? $description : null;
    }
}
