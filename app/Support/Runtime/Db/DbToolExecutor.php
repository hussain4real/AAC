<?php

namespace App\Support\Runtime\Db;

use App\Enums\Environment;
use App\Models\DataSource;
use App\Models\ToolContract;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Database\QueryException;

/**
 * Executes a governed read-only database (`db`) tool: it resolves the tool's
 * approved data source, enforces that the source is active and available in the
 * run's environment and not stale, guards the configured query (single read-only
 * SELECT against the allowlisted query surface, bound parameters only), runs it
 * on the read-only connection under strict row and result-size limits, and
 * returns only the minimized, schema-approved columns. Every failure mode is a
 * controlled {@see ToolExecutionException} so the runtime records a named run
 * failure; the returned array is validated against the tool's output schema by
 * the caller, and its stored copy is redacted there.
 */
class DbToolExecutor
{
    public function __construct(private readonly DataSourceConnection $connections) {}

    /**
     * Execute the read-only query against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    public function execute(ToolContract $tool, ?Environment $environment, array $arguments): array
    {
        $source = $tool->dataSource;

        if (! $source instanceof DataSource) {
            throw ToolExecutionException::dbMisconfigured("The tool [{$tool->slug}] is not mapped to a data source.");
        }

        $envValue = $environment?->value;

        if ($envValue === null || ! $source->isAvailableIn($envValue)) {
            throw ToolExecutionException::dbUnavailable(
                "The data source [{$source->slug}] is disabled or not available in the [".($envValue ?? 'unknown').'] environment.',
            );
        }

        $config = $tool->dbConfig();
        $maxAge = isset($config['max_age_minutes']) ? (int) $config['max_age_minutes'] : null;

        if ($source->isStale($maxAge)) {
            throw ToolExecutionException::dbStale("The data source [{$source->slug}] is stale and may not reflect current data.");
        }

        $query = is_string($config['query'] ?? null) ? $config['query'] : '';
        $statement = DbQueryGuard::assertReadOnlySelect($query, $source->allowedRelations());
        $bindings = DbQueryGuard::bindings($this->bindingNames($config), $arguments);

        $rows = $this->run($source, $tool, $statement, $bindings, $this->columns($config));

        $result = ['rows' => $rows, 'row_count' => count($rows)];

        $this->guardResultSize($result, $this->resultLimitKb($source, $tool));

        return $result;
    }

    /**
     * Run the query on the read-only connection, capping rows at the governed
     * limit and projecting each row to the minimized column set.
     *
     * @param  array<string, mixed>  $bindings
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     *
     * @throws ToolExecutionException
     */
    private function run(DataSource $source, ToolContract $tool, string $statement, array $bindings, array $columns): array
    {
        $limit = $this->rowLimit($source, $tool);
        // Resolved (and connected) outside the query try/catch so a connection
        // failure is reported as its own controlled code.
        $connection = $this->connections->resolve($source);
        $rows = [];

        try {
            foreach ($connection->cursor($statement, $bindings) as $row) {
                if (count($rows) >= $limit) {
                    throw ToolExecutionException::dbTooManyRows($limit);
                }

                $rows[] = $this->project((array) $row, $columns);
            }
        } catch (ToolExecutionException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw ToolExecutionException::dbQueryFailed($exception->getMessage());
        }

        return $rows;
    }

    /**
     * Project a row to the approved (minimized) columns, or return it whole when
     * no projection is configured.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function project(array $row, array $columns): array
    {
        if ($columns === []) {
            return $row;
        }

        $projected = [];

        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $projected[$column] = $row[$column];
            }
        }

        return $projected;
    }

    /**
     * Reject a result that exceeds the governed result-size limit.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws ToolExecutionException
     */
    private function guardResultSize(array $result, int $limitKb): void
    {
        if (strlen((string) json_encode($result)) > $limitKb * 1024) {
            throw ToolExecutionException::dbResultTooLarge($limitKb);
        }
    }

    /**
     * The named bindings the query declares, in order.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function bindingNames(array $config): array
    {
        $names = [];

        foreach ((array) ($config['bindings'] ?? []) as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The minimized output columns the query projects to (empty = all columns).
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function columns(array $config): array
    {
        $columns = [];

        foreach ((array) ($config['columns'] ?? []) as $column) {
            if (is_string($column) && $column !== '') {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * The per-query row limit: the tool's requested limit, capped by the source's
     * hard maximum, and at least one.
     */
    private function rowLimit(DataSource $source, ToolContract $tool): int
    {
        $requested = (int) ($tool->dbConfig()['row_limit'] ?? $source->max_rows);

        return max(1, min($requested, $source->max_rows));
    }

    /**
     * The result-size limit in kilobytes: the source's cap, bounded by the tool's
     * payload limit.
     */
    private function resultLimitKb(DataSource $source, ToolContract $tool): int
    {
        return max(1, min($source->max_result_kb, $tool->max_payload_kb));
    }
}
