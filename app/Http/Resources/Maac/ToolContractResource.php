<?php

namespace App\Http\Resources\Maac;

use App\Enums\ExecMode;
use App\Enums\RemoteAuthType;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a ToolContract to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Tool`). `execMode` and `impl` keep their raw
 * enum values because the frontend keys its label maps on them.
 *
 * @mixin ToolContract
 */
class ToolContractResource extends JsonResource
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
            'scope' => $this->scope->label(),
            'execMode' => $this->execution_mode->value,
            'sensitivity' => $this->sensitivity->label(),
            'approval' => $this->requires_approval,
            'status' => $this->status,
            'impl' => $this->implementation_status->value,
            'owner' => $this->ownerLabel(),
            'appId' => $this->whenLoaded('application', fn () => $this->application?->slug),
            'usedBy' => $this->whenLoaded('agents', fn () => $this->agents->pluck('slug')->all()),
            'desc' => $this->description,
            'timeout' => $this->timeout_seconds.'s',
            'maxPayload' => $this->formattedPayload(),
            'input' => $this->input_schema,
            'output' => $this->output_schema,
            'version' => $this->version,
            // Server-side execution config. Credential material is never returned —
            // only whether auth is configured — so the console can edit safely.
            'httpConfig' => $this->httpConfigView(),
            'connector' => $this->whenLoaded('mcpConnector', fn () => $this->mcpConnector?->slug),
            'connectorName' => $this->whenLoaded('mcpConnector', fn () => $this->mcpConnector?->name),
            'remoteTool' => $this->mcp_tool_name,
            'redaction' => $this->redactionPaths(),
            // Per-environment client-side implementation status reported via the SDK.
            'implementations' => $this->whenLoaded('implementations', fn () => $this->implementations
                ->map(fn (ToolImplementation $implementation): array => [
                    'env' => $implementation->environment->label(),
                    'status' => $implementation->status->value,
                    'handler' => $implementation->handler_name,
                    'version' => $implementation->implemented_version,
                    'language' => $implementation->language?->label(),
                    'lastValidated' => $implementation->last_validated_at?->diffForHumans(),
                ])
                ->values()
                ->all()),
        ];
    }

    /**
     * Build a console-safe view of the remote HTTP config: method, endpoint,
     * auth scheme, and retry policy, with credential material reduced to a
     * "configured" flag so secrets never leave the server.
     *
     * @return array<string, mixed>|null
     */
    private function httpConfigView(): ?array
    {
        if ($this->execution_mode !== ExecMode::Http) {
            return null;
        }

        $config = $this->httpConfig();
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];
        $authType = RemoteAuthType::tryFrom((string) ($auth['type'] ?? 'none')) ?? RemoteAuthType::None;

        return [
            'method' => (string) ($config['method'] ?? 'post'),
            'endpoint' => (string) ($config['endpoint'] ?? ''),
            'authType' => $authType->value,
            'authHeader' => isset($auth['header']) ? (string) $auth['header'] : null,
            'authConfigured' => $authType !== RemoteAuthType::None,
            'maxAttempts' => (int) ($retry['max_attempts'] ?? 1),
            'backoffMs' => (int) ($retry['backoff_ms'] ?? 0),
        ];
    }

    /**
     * Format the max payload (KB) for display, matching the fixture.
     */
    private function formattedPayload(): string
    {
        if ($this->max_payload_kb >= 1024 && $this->max_payload_kb % 1024 === 0) {
            return ($this->max_payload_kb / 1024).' MB';
        }

        return $this->max_payload_kb.' KB';
    }
}
