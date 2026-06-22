<?php

declare(strict_types=1);

namespace Maac\Sdk\Http;

use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\Exceptions\TransportException;

/**
 * The default {@see Transport}: a dependency-free implementation built on PHP's
 * bundled cURL extension, so the SDK can talk to a live MAAC instance from any
 * PHP runtime without pulling in a third-party HTTP client.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $url = $request->url;
        $method = strtoupper($request->method);

        if ($url === '' || $method === '') {
            throw new TransportException('A MAAC request requires a non-empty method and URL.');
        }

        $handle = curl_init();

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->formatHeaders($request->headers));
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_MAXREDIRS, 3);
        // Preserve POST for common HTTP-to-HTTPS 301/302 upgrades, but allow
        // 303 See Other to switch to GET and avoid replaying mutations.
        curl_setopt($handle, CURLOPT_POSTREDIR, CURL_REDIR_POST_301 | CURL_REDIR_POST_302);

        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($handle);

        if ($body === false) {
            $error = curl_error($handle);

            throw new TransportException("Could not reach MAAC at {$url}: {$error}");
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, is_string($body) ? $body : '');
    }

    /**
     * Convert the associative header map into cURL's "Name: value" list form.
     *
     * @param  array<string, string>  $headers
     * @return array<int, string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }

        return $formatted;
    }
}
