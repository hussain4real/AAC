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
}
