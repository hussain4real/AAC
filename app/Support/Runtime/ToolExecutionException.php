<?php

namespace App\Support\Runtime;

use RuntimeException;

/**
 * A controlled failure raised while MAAC executes a server-side tool (remote
 * HTTP or MCP connector). The {@see self::$failureCode} maps directly to the run
 * failure reason the runtime records and returns to the SDK, so every failure
 * mode is observable and named rather than surfacing as a raw exception.
 */
class ToolExecutionException extends RuntimeException
{
    public function __construct(public readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The endpoint failed egress review (invalid URL, blocked host, or not on
     * the allowlist).
     */
    public static function httpBlocked(string $message): self
    {
        return new self('remote_http_blocked', $message);
    }

    /**
     * The remote endpoint could not be reached (connection refused/timeout).
     */
    public static function httpUnreachable(string $message): self
    {
        return new self('remote_http_unreachable', "The remote HTTP tool endpoint is unreachable: {$message}");
    }

    /**
     * The remote endpoint rejected MAAC's credentials.
     */
    public static function httpUnauthorized(int $status): self
    {
        return new self('remote_http_unauthorized', "The remote HTTP tool endpoint returned HTTP {$status} (unauthorized).");
    }

    /**
     * The remote endpoint returned a non-success HTTP status.
     */
    public static function httpFailed(int $status): self
    {
        return new self('remote_http_failed', "The remote HTTP tool endpoint returned HTTP {$status}.");
    }

    /**
     * The remote endpoint returned a body that is not a JSON object.
     */
    public static function httpInvalidOutput(string $message): self
    {
        return new self('remote_http_invalid_output', $message);
    }

    /**
     * The tool is not correctly mapped to a usable connector + remote tool.
     */
    public static function connectorMisconfigured(string $message): self
    {
        return new self('connector_misconfigured', $message);
    }

    /**
     * The mapped connector is disabled or not available in this environment.
     */
    public static function connectorUnavailable(string $message): self
    {
        return new self('connector_unavailable', $message);
    }

    /**
     * The connector server could not be reached (connection/timeout).
     */
    public static function connectorUnreachable(string $message): self
    {
        return new self('connector_unreachable', "The MCP connector is unreachable: {$message}");
    }

    /**
     * The connector server rejected MAAC's credentials.
     */
    public static function connectorUnauthorized(string $message): self
    {
        return new self('connector_unauthorized', "The MCP connector rejected authorization: {$message}");
    }

    /**
     * The connector returned an error result or failed mid-call.
     */
    public static function connectorFailed(string $message): self
    {
        return new self('connector_failed', "The MCP connector tool call failed: {$message}");
    }

    /**
     * The connector returned content that is not a usable JSON object.
     */
    public static function connectorInvalidOutput(string $message): self
    {
        return new self('connector_invalid_output', $message);
    }

    /**
     * The tool is not mapped to a usable knowledge source.
     */
    public static function knowledgeMisconfigured(string $message): self
    {
        return new self('knowledge_misconfigured', $message);
    }

    /**
     * The mapped knowledge source is disabled or not available in this environment.
     */
    public static function knowledgeUnavailable(string $message): self
    {
        return new self('knowledge_unavailable', $message);
    }

    /**
     * The retrieval could not be performed (e.g. an empty query).
     */
    public static function knowledgeFailed(string $message): self
    {
        return new self('knowledge_failed', $message);
    }

    /**
     * The `db` tool is not correctly mapped to a usable, configured data source.
     */
    public static function dbMisconfigured(string $message): self
    {
        return new self('db_source_misconfigured', $message);
    }

    /**
     * The mapped data source is disabled or not available in this environment.
     */
    public static function dbUnavailable(string $message): self
    {
        return new self('db_source_unavailable', $message);
    }

    /**
     * The configured query is not a safe, single read-only statement against the
     * approved query surface.
     */
    public static function dbUnsafeQuery(string $message): self
    {
        return new self('db_unsafe_query', $message);
    }

    /**
     * The read-only connection could not be established.
     */
    public static function dbConnectionFailed(string $message): self
    {
        return new self('db_connection_failed', "The read-only data source connection failed: {$message}");
    }

    /**
     * The query failed to execute against the read-only connection.
     */
    public static function dbQueryFailed(string $message): self
    {
        return new self('db_query_failed', "The read-only data source query failed: {$message}");
    }

    /**
     * The query returned more rows than the governed row limit allows.
     */
    public static function dbTooManyRows(int $limit): self
    {
        return new self('db_too_many_rows', "The query returned more than the {$limit}-row limit.");
    }

    /**
     * The query result exceeds the governed result-size limit.
     */
    public static function dbResultTooLarge(int $limitKb): self
    {
        return new self('db_result_too_large', "The query result exceeds the {$limitKb} KB result-size limit.");
    }

    /**
     * The data source's data is older than the freshness expectation.
     */
    public static function dbStale(string $message): self
    {
        return new self('db_stale', $message);
    }
}
