<?php

namespace App\Http\Resources\Maac;

use App\Enums\RemoteAuthType;
use App\Models\McpConnector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a registered MCP connector for the console. Auth credential
 * material is never exposed — only whether auth is configured and the scheme —
 * so the console can edit the connector without re-displaying the secret.
 *
 * @mixin McpConnector
 */
class McpConnectorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'transport' => $this->transport,
            'serverUrl' => $this->server_url,
            'authType' => $this->auth_type->value,
            'authHeader' => $this->auth_header,
            'authConfigured' => $this->auth_type !== RemoteAuthType::None,
            'sensitivity' => $this->sensitivity->label(),
            'requiresApproval' => $this->requires_approval,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'environments' => array_map(ucfirst(...), $this->environments),
            'capabilities' => array_values($this->capabilities ?? []),
            'toolCount' => $this->whenCounted('tools'),
            'lastDiscovered' => $this->last_discovered_at?->diffForHumans(),
            'owner' => $this->application_id === null ? 'Platform' : $this->whenLoaded('application', fn () => $this->application?->name),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
