<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WebhookEndpointStatus;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterWebhookEndpointRequest;
use App\Models\WebhookEndpoint;
use App\Support\Sdk\SdkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The webhook self-service API: a registered application registers, lists, and
 * removes the endpoints MAACC posts run lifecycle events to. Every endpoint is
 * scoped to the caller's application and credential environment; the signing
 * secret is returned once, on registration, and never again.
 */
class WebhookEndpointController extends Controller
{
    /**
     * List the application's webhook endpoints for its environment.
     */
    public function index(Request $request): JsonResponse
    {
        $context = SdkContext::fromRequest($request);

        $endpoints = WebhookEndpoint::query()
            ->where('application_id', $context->application->id)
            ->where('environment', $context->environment->value)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookEndpoint $endpoint): array => $this->endpointPayload($endpoint))
            ->all();

        return new JsonResponse(['data' => $endpoints]);
    }

    /**
     * Register a new webhook endpoint and return its one-time signing secret.
     */
    public function store(RegisterWebhookEndpointRequest $request): JsonResponse
    {
        $context = SdkContext::fromRequest($request);

        $secret = WebhookEndpoint::generateSecret();

        $endpoint = new WebhookEndpoint([
            'application_id' => $context->application->id,
            'environment' => $context->environment,
            'url' => $request->webhookUrl(),
            'events' => $request->events(),
            'description' => $request->description(),
            'status' => WebhookEndpointStatus::Active,
        ]);
        $endpoint->fillSecret($secret);
        $endpoint->save();

        return new JsonResponse([
            'secret' => $secret,
            ...$this->endpointPayload($endpoint),
        ], 201);
    }

    /**
     * Delete a webhook endpoint the application owns.
     */
    public function destroy(Request $request, string $webhookEndpoint): Response
    {
        $context = SdkContext::fromRequest($request);

        $endpoint = WebhookEndpoint::query()
            ->where('id', $webhookEndpoint)
            ->where('application_id', $context->application->id)
            ->first();

        if (! $endpoint instanceof WebhookEndpoint) {
            throw RuntimeRequestException::webhookEndpointNotFound();
        }

        $endpoint->delete();

        return response()->noContent();
    }

    /**
     * The runtime API representation of a webhook endpoint (never the secret).
     *
     * @return array<string, mixed>
     */
    private function endpointPayload(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'environment' => $endpoint->environment->value,
            'status' => $endpoint->status->value,
            'description' => $endpoint->description,
            'last_delivered_at' => $endpoint->last_delivered_at?->toIso8601String(),
            'created_at' => $endpoint->created_at?->toIso8601String(),
        ];
    }
}
