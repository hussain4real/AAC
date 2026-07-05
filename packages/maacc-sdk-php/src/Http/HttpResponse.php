<?php

declare(strict_types=1);

namespace Maacc\Sdk\Http;

use JsonException;
use Maacc\Sdk\Contracts\Transport;
use Maacc\Sdk\Exceptions\TransportException;

/**
 * An immutable HTTP response returned by a {@see Transport}.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {}

    /**
     * Whether the status code is in the 2xx success range.
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Decode the response body as a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws TransportException when the body is not a JSON object
     */
    public function json(): array
    {
        try {
            $decoded = json_decode($this->body === '' ? '{}' : $this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TransportException('MAACC returned a non-JSON response (HTTP '.$this->status.'): '.$exception->getMessage(), $this->status, $exception);
        }

        if (! is_array($decoded)) {
            throw new TransportException('MAACC returned a non-object JSON response (HTTP '.$this->status.').', $this->status);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
