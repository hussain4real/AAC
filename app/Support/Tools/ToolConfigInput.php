<?php

namespace App\Support\Tools;

use App\Enums\ExecMode;
use App\Models\ToolContract;

/**
 * Normalizes validated tool-contract input into the persisted execution config:
 * it keeps only the config relevant to the chosen execution mode and preserves
 * write-only HTTP auth credentials when an edit does not re-submit them (so the
 * console never has to re-display a secret to keep it).
 */
class ToolConfigInput
{
    /**
     * Normalize the validated data for persistence against the (optional) existing
     * contract.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data, ?ToolContract $existing = null): array
    {
        $mode = $data['execution_mode'] ?? $existing?->execution_mode?->value;

        if ($mode === ExecMode::Http->value) {
            $data['http_config'] = self::httpConfig(
                is_array($data['http_config'] ?? null) ? $data['http_config'] : [],
                $existing?->httpConfig() ?? [],
            );
            $data['mcp_connector_id'] = null;
            $data['mcp_tool_name'] = null;

            return $data;
        }

        if ($mode === ExecMode::Connector->value) {
            $data['http_config'] = null;

            return $data;
        }

        $data['http_config'] = null;
        $data['mcp_connector_id'] = null;
        $data['mcp_tool_name'] = null;

        return $data;
    }

    /**
     * Build the stored HTTP config, preserving the existing credential when the
     * submission leaves it blank.
     *
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private static function httpConfig(array $submitted, array $existing): array
    {
        $submittedAuth = is_array($submitted['auth'] ?? null) ? $submitted['auth'] : [];
        $existingAuth = is_array($existing['auth'] ?? null) ? $existing['auth'] : [];

        $type = (string) ($submittedAuth['type'] ?? ($existingAuth['type'] ?? 'none'));
        $credential = (string) ($submittedAuth['credential'] ?? '');

        if ($credential === '') {
            $credential = (string) ($existingAuth['credential'] ?? '');
        }

        $submittedRetry = is_array($submitted['retry'] ?? null) ? $submitted['retry'] : [];
        $existingRetry = is_array($existing['retry'] ?? null) ? $existing['retry'] : [];

        return [
            'method' => (string) ($submitted['method'] ?? ($existing['method'] ?? 'post')),
            'endpoint' => (string) ($submitted['endpoint'] ?? ($existing['endpoint'] ?? '')),
            'auth' => [
                'type' => $type,
                'header' => (string) ($submittedAuth['header'] ?? ($existingAuth['header'] ?? '')),
                'credential' => $credential,
            ],
            'retry' => [
                'max_attempts' => (int) ($submittedRetry['max_attempts'] ?? ($existingRetry['max_attempts'] ?? 1)),
                'backoff_ms' => (int) ($submittedRetry['backoff_ms'] ?? ($existingRetry['backoff_ms'] ?? 0)),
            ],
        ];
    }
}
