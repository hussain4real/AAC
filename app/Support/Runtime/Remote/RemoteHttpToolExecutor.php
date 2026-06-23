<?php

namespace App\Support\Runtime\Remote;

use App\Enums\HttpMethod;
use App\Enums\RemoteAuthType;
use App\Models\ToolContract;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Executes a remote HTTP tool: it enforces the egress allowlist, applies the
 * contract's method/auth/timeout/retry policy, calls the remote endpoint, and
 * returns the decoded JSON object. Every failure mode is a controlled
 * {@see ToolExecutionException} so the runtime can record a named run failure.
 * The returned array is validated against the tool's output schema by the caller.
 */
class RemoteHttpToolExecutor
{
    /**
     * Execute the tool against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    public function execute(ToolContract $tool, array $arguments): array
    {
        $config = $tool->httpConfig();
        $endpoint = is_string($config['endpoint'] ?? null) ? $config['endpoint'] : '';
        $method = HttpMethod::tryFrom((string) ($config['method'] ?? '')) ?? HttpMethod::Post;

        $this->guardEgress($endpoint);

        return $this->parse($this->sendWithRetry($tool, $config, $method, $endpoint, $arguments));
    }

    /**
     * Verify the endpoint is a valid HTTP(S) URL whose host is not denied and is
     * present on the configured egress allowlist.
     *
     * @throws ToolExecutionException
     */
    private function guardEgress(string $endpoint): void
    {
        $host = Str::lower((string) parse_url($endpoint, PHP_URL_HOST));
        $scheme = Str::lower((string) parse_url($endpoint, PHP_URL_SCHEME));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw ToolExecutionException::httpBlocked('The remote HTTP tool endpoint is not a valid HTTP(S) URL.');
        }

        if ($this->matchesAny($host, $this->blockedHosts())) {
            throw ToolExecutionException::httpBlocked("The remote HTTP tool endpoint host [{$host}] is blocked.");
        }

        if (! $this->matchesAny($host, $this->allowedHosts())) {
            throw ToolExecutionException::httpBlocked("The remote HTTP tool endpoint host [{$host}] is not on the egress allowlist.");
        }
    }

    /**
     * Send the request, retrying connection failures and 5xx responses up to the
     * configured attempt limit, and translate the outcome into a response or a
     * controlled exception.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $arguments
     *
     * @throws ToolExecutionException
     */
    private function sendWithRetry(ToolContract $tool, array $config, HttpMethod $method, string $endpoint, array $arguments): Response
    {
        $attempts = $this->attempts($config);
        $backoffMs = $this->backoffMs($config);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->dispatch($tool, $config, $method, $endpoint, $arguments);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    usleep($backoffMs * 1000);

                    continue;
                }

                throw ToolExecutionException::httpUnreachable($exception->getMessage());
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw ToolExecutionException::httpUnauthorized($response->status());
            }

            if ($response->serverError() && $attempt < $attempts) {
                usleep($backoffMs * 1000);

                continue;
            }

            if (! $response->successful()) {
                throw ToolExecutionException::httpFailed($response->status());
            }

            return $response;
        }
    }

    /**
     * Build and send a single HTTP request honoring the auth and timeout policy.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $arguments
     *
     * @throws ConnectionException
     */
    private function dispatch(ToolContract $tool, array $config, HttpMethod $method, string $endpoint, array $arguments): Response
    {
        $request = $this->authenticate(
            Http::acceptJson()
                ->asJson()
                ->timeout($tool->timeout_seconds)
                ->connectTimeout($this->connectTimeout()),
            is_array($config['auth'] ?? null) ? $config['auth'] : [],
        );

        return match ($method) {
            HttpMethod::Get => $request->get($endpoint, $arguments),
            HttpMethod::Post => $request->post($endpoint, $arguments),
            HttpMethod::Put => $request->put($endpoint, $arguments),
            HttpMethod::Patch => $request->patch($endpoint, $arguments),
            HttpMethod::Delete => $request->delete($endpoint, $arguments),
        };
    }

    /**
     * Apply the configured authentication scheme to the pending request.
     *
     * @param  array<string, mixed>  $auth
     */
    private function authenticate(PendingRequest $request, array $auth): PendingRequest
    {
        $type = RemoteAuthType::tryFrom((string) ($auth['type'] ?? 'none')) ?? RemoteAuthType::None;
        $credential = (string) ($auth['credential'] ?? '');
        $header = (string) ($auth['header'] ?? '');

        return match ($type) {
            RemoteAuthType::None => $request,
            RemoteAuthType::Bearer => $request->withToken($credential),
            RemoteAuthType::Header => $request->withHeaders([
                ($header !== '' ? $header : 'Authorization') => $credential,
            ]),
        };
    }

    /**
     * Decode the response body into a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    private function parse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw ToolExecutionException::httpInvalidOutput('The remote HTTP tool endpoint did not return a JSON object.');
        }

        return $data;
    }

    /**
     * Determine whether the host matches any pattern (exact or `*.` wildcard).
     *
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = Str::lower(trim($pattern));

            if ($pattern === '') {
                continue;
            }

            if (Str::startsWith($pattern, '*.')) {
                if (Str::endsWith($host, Str::substr($pattern, 1))) {
                    return true;
                }

                continue;
            }

            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * The egress allowlist of permitted endpoint hosts.
     *
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        return (array) config('maac.runtime.remote_http.allowed_hosts', []);
    }

    /**
     * The egress denylist (loopback/link-local/metadata) that overrides allow.
     *
     * @return array<int, string>
     */
    private function blockedHosts(): array
    {
        return (array) config('maac.runtime.remote_http.blocked_hosts', []);
    }

    /**
     * Resolve the per-tool attempt count, capped by the platform maximum.
     *
     * @param  array<string, mixed>  $config
     */
    private function attempts(array $config): int
    {
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];
        $requested = max(1, (int) ($retry['max_attempts'] ?? 1));
        $max = max(1, (int) config('maac.runtime.remote_http.max_attempts', 3));

        return min($requested, $max);
    }

    /**
     * Resolve the per-tool backoff between retries, in milliseconds.
     *
     * @param  array<string, mixed>  $config
     */
    private function backoffMs(array $config): int
    {
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];

        return max(0, (int) ($retry['backoff_ms'] ?? 0));
    }

    /**
     * The TCP connect timeout, in seconds, for an endpoint call.
     */
    private function connectTimeout(): int
    {
        return max(1, (int) config('maac.runtime.remote_http.connect_timeout_seconds', 5));
    }
}
