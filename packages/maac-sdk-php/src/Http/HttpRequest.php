<?php

declare(strict_types=1);

namespace Maac\Sdk\Http;

use Maac\Sdk\Contracts\Transport;

/**
 * An immutable description of an outbound HTTP request. The SDK builds these and
 * hands them to a {@see Transport}; nothing here is bound to
 * a framework.
 */
final class HttpRequest
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
    ) {}

    /**
     * Build a JSON request, encoding the payload and setting the content type.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public static function json(string $method, string $url, array $payload, array $headers = []): self
    {
        return new self($method, $url, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            ...$headers,
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Build a form-urlencoded request (used for the OAuth token exchange).
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $headers
     */
    public static function form(string $method, string $url, array $fields, array $headers = []): self
    {
        return new self($method, $url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
            ...$headers,
        ], http_build_query($fields));
    }

    /**
     * Build a request with a bearer token applied.
     *
     * @param  array<string, string>  $headers
     */
    public static function bearer(string $method, string $url, string $token, array $headers = []): self
    {
        return new self($method, $url, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
            ...$headers,
        ]);
    }

    /**
     * Return a copy of this request with the given header set.
     */
    public function withHeader(string $name, string $value): self
    {
        return new self($this->method, $this->url, [...$this->headers, $name => $value], $this->body);
    }
}
