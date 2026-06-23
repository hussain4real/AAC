<?php

namespace App\Http\Requests\Maac\Concerns;

use App\Enums\HttpMethod;
use App\Enums\RemoteAuthType;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for the server-side tool execution config (remote HTTP
 * + MCP connector mapping + result redaction), used by the store and update tool
 * contract requests. The connector reference is scoped to the caller's team to
 * prevent cross-tenant mapping.
 */
trait ValidatesToolConfig
{
    /**
     * Build the validation rules for the execution config fields.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function toolConfigRules(?int $teamId): array
    {
        return [
            'http_config' => ['nullable', 'array', 'required_if:execution_mode,http'],
            'http_config.method' => ['nullable', 'required_if:execution_mode,http', Rule::enum(HttpMethod::class)],
            'http_config.endpoint' => ['nullable', 'required_if:execution_mode,http', 'url', 'max:2048'],
            'http_config.auth' => ['nullable', 'array'],
            'http_config.auth.type' => ['nullable', Rule::enum(RemoteAuthType::class)],
            'http_config.auth.header' => ['nullable', 'string', 'max:128'],
            'http_config.auth.credential' => ['nullable', 'string', 'max:2048'],
            'http_config.retry' => ['nullable', 'array'],
            'http_config.retry.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'http_config.retry.backoff_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'mcp_connector_id' => [
                'nullable',
                'required_if:execution_mode,connector',
                'uuid',
                Rule::exists('mcp_connectors', 'id')->where('team_id', $teamId),
            ],
            'mcp_tool_name' => ['nullable', 'required_if:execution_mode,connector', 'string', 'max:128'],
            'redaction' => ['nullable', 'array'],
            'redaction.*' => ['string', 'max:128'],
        ];
    }
}
