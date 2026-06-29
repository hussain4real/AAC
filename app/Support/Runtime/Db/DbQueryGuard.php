<?php

namespace App\Support\Runtime\Db;

use App\Support\Runtime\ToolExecutionException;
use Illuminate\Support\Str;

/**
 * Static guards that make a governed `db` tool query safe to run: the query must
 * be a single read-only SELECT/CTE, must contain no write/DDL/transaction
 * keywords, and may only reference the data source's allowlisted relations. The
 * model never writes raw SQL — the query template is authored and approved in
 * MAAC — but these guards are defence-in-depth so an unsafe template, or one
 * referencing a relation outside the approved surface, fails before execution.
 * Values are always bound as parameters; the guards never permit interpolation.
 */
class DbQueryGuard
{
    /**
     * Keywords that would make a statement a write, DDL, transaction-control, or
     * stacked operation. Matched as whole words against a literal-stripped copy.
     *
     * @var array<int, string>
     */
    private const BLOCKED_KEYWORDS = [
        'insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'create',
        'grant', 'revoke', 'merge', 'replace', 'call', 'exec', 'execute',
        'into', 'attach', 'detach', 'pragma', 'copy', 'vacuum', 'commit',
        'rollback', 'savepoint', 'set', 'lock', 'rename', 'upsert', 'reindex',
    ];

    /**
     * Assert the query is a single read-only SELECT against the approved query
     * surface, returning the statement to execute (its trailing `;` stripped).
     *
     * @param  array<int, string>  $allowedRelations
     *
     * @throws ToolExecutionException
     */
    public static function assertReadOnlySelect(string $sql, array $allowedRelations): string
    {
        $statement = self::trimStatement($sql);

        if ($statement === '') {
            throw ToolExecutionException::dbUnsafeQuery('The data source query is empty.');
        }

        $analysis = self::analysisForm($statement);

        if (! Str::startsWith($analysis, ['select ', 'select(', 'with '])) {
            throw ToolExecutionException::dbUnsafeQuery('The data source query must be a read-only SELECT statement.');
        }

        if (Str::contains($statement, ';')) {
            throw ToolExecutionException::dbUnsafeQuery('The data source query must be a single statement.');
        }

        if (Str::contains($statement, ['--', '/*', '#'])) {
            throw ToolExecutionException::dbUnsafeQuery('The data source query must not contain SQL comments.');
        }

        self::assertNoBlockedKeywords($analysis);
        self::assertAllowedRelations($analysis, $allowedRelations);

        return $statement;
    }

    /**
     * Resolve the named parameter bindings for the query from the validated tool
     * arguments, in the order the config declares. A declared binding that is
     * absent from the arguments is a query-surface misconfiguration.
     *
     * @param  array<int, string>  $bindingNames
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    public static function bindings(array $bindingNames, array $arguments): array
    {
        $bindings = [];

        foreach ($bindingNames as $name) {
            if (! array_key_exists($name, $arguments)) {
                throw ToolExecutionException::dbUnsafeQuery("The data source query is missing the bound argument [{$name}].");
            }

            $bindings[$name] = $arguments[$name];
        }

        return $bindings;
    }

    /**
     * Trim surrounding whitespace and a single trailing statement terminator.
     */
    private static function trimStatement(string $sql): string
    {
        return rtrim(trim($sql), ';');
    }

    /**
     * Produce the lowercased, literal-stripped form used for keyword/relation
     * analysis, so a value like `'deleted'` never trips the write-keyword guard.
     */
    private static function analysisForm(string $statement): string
    {
        $withoutLiterals = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $statement);

        return Str::lower($withoutLiterals);
    }

    /**
     * Reject any write/DDL/transaction/stacked keyword present as a whole word.
     *
     * @throws ToolExecutionException
     */
    private static function assertNoBlockedKeywords(string $analysis): void
    {
        foreach (self::BLOCKED_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $analysis) === 1) {
                throw ToolExecutionException::dbUnsafeQuery("The data source query contains the disallowed keyword [{$keyword}].");
            }
        }
    }

    /**
     * Assert every relation the query reads from is on the source's allowlist.
     *
     * @param  array<int, string>  $allowedRelations
     *
     * @throws ToolExecutionException
     */
    private static function assertAllowedRelations(string $analysis, array $allowedRelations): void
    {
        $allowed = array_map(fn (string $relation): string => Str::lower(trim($relation, " \t\n\r\0\x0B\"`[]")), $allowedRelations);

        preg_match_all('/\b(?:from|join)\s+([a-z_][a-z0-9_."`\[\]]*)/', $analysis, $matches);

        $referenced = array_values(array_unique($matches[1]));

        if ($referenced === []) {
            throw ToolExecutionException::dbUnsafeQuery('The data source query does not read from an approved relation.');
        }

        foreach ($referenced as $relation) {
            $name = Str::lower(trim($relation, '"`[]'));

            if (! in_array($name, $allowed, true)) {
                throw ToolExecutionException::dbUnsafeQuery("The data source query references the relation [{$name}] which is not on the approved query surface.");
            }
        }
    }
}
