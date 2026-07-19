<?php

namespace App\Support\Runtime\Remote;

use App\Enums\HttpMethod;
use App\Enums\RemoteAuthType;
use App\Exceptions\OutboundRequestBlocked;
use App\Models\ToolContract;
use App\Support\Outbound\OutboundHttpClient;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Executes a remote HTTP tool: it enforces the egress allowlist, applies the
 * contract's method/auth/timeout/retry policy, calls the remote endpoint, and
 * returns the decoded JSON object. Every failure mode is a controlled
 * {@see ToolExecutionException} so the runtime can record a named run failure.
 * The returned array is validated against the tool's output schema by the caller.
 */
class RemoteHttpToolExecutor
{
    public function __construct(private readonly OutboundHttpClient $http) {}

    /**
     * Execute the tool against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     * @throws OutboundRequestBlocked
     */
    public function execute(ToolContract $tool, array $arguments): array
    {
        $config = $tool->httpConfig();
        $endpoint = is_string($config['endpoint'] ?? null) ? $config['endpoint'] : '';
        $method = HttpMethod::tryFrom((string) ($config['method'] ?? '')) ?? HttpMethod::Post;

        try {
            return $this->parse($this->sendWithRetry($tool, $config, $method, $endpoint, $arguments));
        } catch (OutboundRequestBlocked $exception) {
            throw ToolExecutionException::httpBlocked($exception->reason);
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
     * @throws OutboundRequestBlocked
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
     * @throws OutboundRequestBlocked
     */
    private function dispatch(ToolContract $tool, array $config, HttpMethod $method, string $endpoint, array $arguments): Response
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                ...$this->authHeaders(is_array($config['auth'] ?? null) ? $config['auth'] : []),
            ],
            'timeout' => $tool->timeout_seconds,
            'connect_timeout' => $this->connectTimeout(),
            'allowed_hosts' => $this->allowedHosts(),
            'max_redirects' => 2,
        ];
        $options[$method === HttpMethod::Get ? 'query' : 'json'] = $arguments;

        return $this->http->send('remote_http', $method->value, $endpoint, $options);
    }

    /**
     * Apply the configured authentication scheme to the pending request.
     *
     * @param  array<string, mixed>  $auth
     * @return array<string, string>
     */
    private function authHeaders(array $auth): array
    {
        $type = RemoteAuthType::tryFrom((string) ($auth['type'] ?? 'none')) ?? RemoteAuthType::None;
        $credential = (string) ($auth['credential'] ?? '');
        $header = (string) ($auth['header'] ?? '');

        return match ($type) {
            RemoteAuthType::None => [],
            RemoteAuthType::Bearer => ['Authorization' => "Bearer {$credential}"],
            RemoteAuthType::Header => [($header !== '' ? $header : 'Authorization') => $credential],
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
     * The egress allowlist of permitted endpoint hosts.
     *
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        return (array) config('maacc.runtime.remote_http.allowed_hosts', []);
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
        $max = max(1, (int) config('maacc.runtime.remote_http.max_attempts', 3));

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
        return max(1, (int) config('maacc.runtime.remote_http.connect_timeout_seconds', 5));
    }
}
