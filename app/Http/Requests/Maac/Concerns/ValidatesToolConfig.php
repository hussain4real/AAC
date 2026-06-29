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
            'knowledge_source_id' => [
                'nullable',
                'required_if:execution_mode,knowledge',
                'uuid',
                Rule::exists('knowledge_sources', 'id')->where('team_id', $teamId),
            ],
            'knowledge_config' => ['nullable', 'array'],
            'knowledge_config.top_k' => ['nullable', 'integer', 'min:1', 'max:50'],
            'knowledge_config.min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'data_source_id' => [
                'nullable',
                'required_if:execution_mode,db',
                'uuid',
                Rule::exists('data_sources', 'id')->where('team_id', $teamId),
            ],
            'db_config' => ['nullable', 'array', 'required_if:execution_mode,db'],
            'db_config.query' => ['nullable', 'required_if:execution_mode,db', 'string', 'max:4000'],
            'db_config.bindings' => ['nullable', 'array'],
            'db_config.bindings.*' => ['string', 'max:64'],
            'db_config.columns' => ['nullable', 'array'],
            'db_config.columns.*' => ['string', 'max:128'],
            'db_config.row_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'db_config.max_age_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'redaction' => ['nullable', 'array'],
            'redaction.*' => ['string', 'max:128'],
        ];
    }
}
