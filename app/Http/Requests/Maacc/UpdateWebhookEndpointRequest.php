<?php

namespace App\Http\Requests\Maacc;

use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a console webhook endpoint update (destination, subscribed events,
 * description, and enabled/disabled status). Authorization is on the controller.
 */
class UpdateWebhookEndpointRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url:http,https', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in([...WebhookEventType::values(), '*'])],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(WebhookEndpointStatus::class)],
        ];
    }
}
