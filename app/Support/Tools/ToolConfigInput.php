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

            return self::withoutDb(self::withoutConnector(self::withoutKnowledge($data)));
        }

        if ($mode === ExecMode::Connector->value) {
            $data['http_config'] = null;

            return self::withoutDb(self::withoutKnowledge($data));
        }

        if ($mode === ExecMode::Knowledge->value) {
            $data['http_config'] = null;
            $data['knowledge_config'] = self::knowledgeConfig(
                is_array($data['knowledge_config'] ?? null) ? $data['knowledge_config'] : [],
            );

            return self::withoutDb(self::withoutConnector($data));
        }

        if ($mode === ExecMode::Db->value) {
            $data['http_config'] = null;
            $data['db_config'] = self::dbConfig(
                is_array($data['db_config'] ?? null) ? $data['db_config'] : [],
            );

            return self::withoutKnowledge(self::withoutConnector($data));
        }

        $data['http_config'] = null;

        return self::withoutDb(self::withoutKnowledge(self::withoutConnector($data)));
    }

    /**
     * Null the connector mapping (kept only for connector-mode contracts).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutConnector(array $data): array
    {
        $data['mcp_connector_id'] = null;
        $data['mcp_tool_name'] = null;

        return $data;
    }

    /**
     * Null the knowledge mapping (kept only for knowledge-mode contracts).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutKnowledge(array $data): array
    {
        $data['knowledge_source_id'] = null;
        $data['knowledge_config'] = null;

        return $data;
    }

    /**
     * Null the data-source mapping (kept only for db-mode contracts).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutDb(array $data): array
    {
        $data['data_source_id'] = null;
        $data['db_config'] = null;

        return $data;
    }

    /**
     * Build the stored knowledge-retrieval config (defaults applied at runtime
     * when a value is omitted).
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private static function knowledgeConfig(array $submitted): array
    {
        return [
            'top_k' => max(1, (int) ($submitted['top_k'] ?? config('maac.runtime.knowledge.default_top_k', 5))),
            'min_score' => round((float) ($submitted['min_score'] ?? config('maac.runtime.knowledge.default_min_score', 0.1)), 4),
        ];
    }

    /**
     * Build the stored read-only database config: the parameterized SELECT
     * template, its declared bindings, the projected (minimized) columns, the
     * per-query row limit, and the optional freshness expectation. The config
     * holds no secrets (the credential lives in the vault via the data source).
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private static function dbConfig(array $submitted): array
    {
        return [
            'query' => trim((string) ($submitted['query'] ?? '')),
            'bindings' => self::stringList($submitted['bindings'] ?? []),
            'columns' => self::stringList($submitted['columns'] ?? []),
            'row_limit' => max(1, (int) ($submitted['row_limit'] ?? config('maac.runtime.db.default_row_limit', 50))),
            'max_age_minutes' => self::nullableInt($submitted['max_age_minutes'] ?? null),
        ];
    }

    /**
     * Filter a value into a clean list of non-empty strings.
     *
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        $list = [];

        foreach ((array) $value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return $list;
    }

    /**
     * Coerce a submitted value into a positive integer, or null when unset/zero.
     */
    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
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
