<?php

declare(strict_types=1);

namespace Tests\Support\Sdk;

use Illuminate\Foundation\Testing\TestCase;
use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\Http\HttpRequest;
use Maac\Sdk\Http\HttpResponse;

/**
 * A {@see Transport} that dispatches the SDK's requests through the MAAC
 * application's real HTTP kernel in-process, instead of over a socket. Because
 * it runs the full middleware stack (including Passport token validation and the
 * `sdk.auth` resolver) against a genuinely issued access token, the SDK is
 * exercised against the real runtime contract — the only difference from
 * production being the absence of a network hop. This is test-only glue: the SDK
 * package itself knows nothing about Laravel.
 */
final class KernelTransport implements Transport
{
    public function __construct(private readonly TestCase $test) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $contentType = $request->headers['Content-Type'] ?? '';
        $isForm = str_starts_with($contentType, 'application/x-www-form-urlencoded');

        $parameters = [];
        $content = $request->body;

        if ($isForm && $request->body !== null) {
            parse_str($request->body, $parameters);
            $content = null;
        }

        $response = $this->test->call(
            $request->method,
            $request->url,
            $parameters,
            [],
            [],
            $this->serverVars($request->headers),
            $content,
        );

        return new HttpResponse($response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * Translate the request's header map into PHP server variables, mirroring
     * how a real web server would expose them.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function serverVars(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));

            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = $value;
        }

        return $server;
    }
}
