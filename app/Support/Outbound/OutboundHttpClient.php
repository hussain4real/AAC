<?php

namespace App\Support\Outbound;

use App\Exceptions\OutboundRequestBlocked;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OutboundHttpClient
{
    public function __construct(private readonly OutboundRequestPolicy $policy) {}

    /**
     * Send an outbound request with DNS pinning and hop-by-hop policy checks.
     *
     * @param  array{
     *     headers?: array<string, string>,
     *     query?: array<string, mixed>,
     *     json?: array<string, mixed>,
     *     form?: array<string, mixed>,
     *     body?: string,
     *     timeout?: int|float,
     *     connect_timeout?: int|float,
     *     max_redirects?: int,
     *     allowed_hosts?: array<int, string>,
     *     stream?: bool
     * }  $options
     *
     * @throws OutboundRequestBlocked
     */
    public function send(string $purpose, string $method, string $url, array $options = []): Response
    {
        $headers = $options['headers'] ?? [];
        $allowedHosts = $options['allowed_hosts'] ?? [];
        $maxRedirects = max(0, (int) ($options['max_redirects'] ?? config('maacc.outbound.max_redirects', 3)));
        $redirects = 0;
        $currentMethod = strtoupper($method);
        $currentUrl = $url;
        $requestData = $this->requestData($options);

        while (true) {
            $destination = $this->policy->inspect($currentUrl, $purpose, $allowedHosts);
            $requestOptions = [
                ...$destination->guzzleOptions(),
                'stream' => (bool) ($options['stream'] ?? false),
            ];
            $request = Http::withOptions($requestOptions)
                ->connectTimeout((float) ($options['connect_timeout'] ?? config('maacc.outbound.connect_timeout_seconds', 3)))
                ->timeout((float) ($options['timeout'] ?? config('maacc.outbound.timeout_seconds', 10)))
                ->withHeaders($headers);
            $response = $request->send($currentMethod, $currentUrl, $requestData);

            if (! $this->isRedirect($response)) {
                return $response;
            }

            $location = $response->header('Location');

            if ($location === '') {
                return $response;
            }

            if ($redirects >= $maxRedirects) {
                throw new OutboundRequestBlocked('the destination exceeded the approved redirect limit');
            }

            $nextUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
            $nextDestination = $this->policy->inspect($nextUrl, $purpose, $allowedHosts);

            if (! hash_equals($destination->host, $nextDestination->host)) {
                $headers = $this->stripSensitiveHeaders($headers);
            }

            if (in_array($response->status(), [301, 302, 303], true) && $currentMethod !== 'GET' && $currentMethod !== 'HEAD') {
                $currentMethod = 'GET';
                $requestData = [];
            }

            $currentUrl = $nextUrl;
            $redirects++;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function requestData(array $options): array
    {
        if (is_array($options['json'] ?? null)) {
            return ['json' => $options['json']];
        }

        if (is_array($options['form'] ?? null)) {
            return ['form_params' => $options['form']];
        }

        if (is_string($options['body'] ?? null)) {
            return ['body' => $options['body']];
        }

        $query = $options['query'] ?? null;

        return is_array($query) ? ['query' => $query] : [];
    }

    private function isRedirect(Response $response): bool
    {
        return $response->status() >= 300 && $response->status() < 400;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function stripSensitiveHeaders(array $headers): array
    {
        $sensitive = array_map('strtolower', [
            'authorization',
            'proxy-authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
            ...(array) config('maacc.outbound.sensitive_headers', []),
        ]);

        return array_filter(
            $headers,
            fn (string $name): bool => ! in_array(strtolower($name), $sensitive, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
