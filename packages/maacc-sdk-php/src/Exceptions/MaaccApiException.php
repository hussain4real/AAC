<?php

declare(strict_types=1);

namespace Maacc\Sdk\Exceptions;

use Maacc\Sdk\Http\HttpResponse;

/**
 * Represents a controlled error MAACC returned for a request — an authentication
 * failure, an unknown agent, an oversized payload, a quota breach, and so on.
 * The MAACC error code and HTTP status are preserved so callers can branch on
 * them programmatically rather than parsing a message.
 *
 * @see https for the canonical error codes: credential_revoked, invalid_token,
 *      unknown_client, agent_not_found, agent_not_published, run_not_found,
 *      run_not_waiting, payload_too_large, quota_exceeded, invalid_tool_result.
 */
class MaaccApiException extends MaaccException
{
    /**
     * @param  array<string, mixed>  $payload  The full decoded error envelope.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $payload = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Build the exception from a non-2xx MAACC response, falling back gracefully
     * when the body is not the standard `{ error, message }` envelope (for
     * example a framework validation 422).
     */
    public static function fromResponse(HttpResponse $response): self
    {
        $payload = self::decode($response);

        $code = is_string($payload['error'] ?? null) ? $payload['error'] : 'http_error';
        $message = is_string($payload['message'] ?? null)
            ? $payload['message']
            : 'MAACC returned HTTP '.$response->status.'.';

        return new self($code, $message, $response->status, $payload);
    }

    /**
     * The schema-validation messages MAACC attached to an invalid_tool_result.
     *
     * @return array<int, string>
     */
    public function validationErrors(): array
    {
        $errors = $this->payload['errors'] ?? [];

        return is_array($errors) ? array_values(array_filter($errors, 'is_string')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(HttpResponse $response): array
    {
        try {
            return $response->json();
        } catch (TransportException) {
            return [];
        }
    }
}
