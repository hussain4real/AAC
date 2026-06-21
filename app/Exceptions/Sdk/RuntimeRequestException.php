<?php

namespace App\Exceptions\Sdk;

use App\Support\Sdk\SdkError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A controlled, client-facing failure in the SDK/runtime API. Carries the SDK
 * error code and HTTP status, and renders itself into the standard
 * {@see SdkError} envelope, so controllers can simply throw it.
 */
class RuntimeRequestException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    /**
     * The requested agent could not be found for the caller's application.
     */
    public static function agentNotFound(): self
    {
        return new self('agent_not_found', 'No published agent matches the given identifier for this application.', 404);
    }

    /**
     * The agent exists but is not published for runtime invocation.
     */
    public static function agentNotPublished(): self
    {
        return new self('agent_not_published', 'The agent is not published and cannot be invoked.', 409);
    }

    /**
     * The requested run could not be found for the caller's application.
     */
    public static function runNotFound(): self
    {
        return new self('run_not_found', 'No run matches the given identifier for this application.', 404);
    }

    /**
     * The run is not paused waiting for a client-side tool result.
     */
    public static function runNotWaiting(): self
    {
        return new self('run_not_waiting', 'The run is not waiting for a client-side tool result.', 409);
    }

    /**
     * The submitted tool result exceeds the contract's payload size limit.
     */
    public static function payloadTooLarge(): self
    {
        return new self('payload_too_large', 'The submitted tool result exceeds the contract payload limit.', 413);
    }

    /**
     * A configured rate limit / quota for the run has been reached.
     */
    public static function quotaExceeded(string $detail): self
    {
        return new self('quota_exceeded', "The {$detail} has been reached for this period.", 429);
    }

    /**
     * The submitted tool result failed output-schema validation.
     *
     * @param  array<int, string>  $errors
     */
    public static function invalidToolResult(array $errors): self
    {
        return new self('invalid_tool_result', 'The submitted tool result does not satisfy the tool output schema.', 422, [
            'errors' => $errors,
        ]);
    }

    /**
     * Render the exception into the standard SDK error envelope.
     */
    public function render(Request $request): JsonResponse
    {
        return SdkError::response($this->errorCode, $this->getMessage(), $this->status, $this->extra);
    }
}
