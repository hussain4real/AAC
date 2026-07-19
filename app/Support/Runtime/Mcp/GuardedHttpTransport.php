<?php

namespace App\Support\Runtime\Mcp;

use App\Exceptions\OutboundRequestBlocked;
use App\Support\Outbound\OutboundHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\OAuth\WwwAuthenticateChallenge;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Throwable;

class GuardedHttpTransport extends HttpTransport
{
    public function __construct(
        string $url,
        private readonly OutboundHttpClient $http,
        private readonly int $connectTimeoutSeconds = 3,
    ) {
        parent::__construct($url);
    }

    public function send(string $message): void
    {
        $hadSession = $this->sessionId !== null;

        try {
            $response = $this->http->send('mcp', 'POST', $this->url, [
                'headers' => $this->headers(),
                'body' => $message,
                'timeout' => $this->timeoutSeconds,
                'connect_timeout' => $this->connectTimeoutSeconds,
                'max_redirects' => 0,
                'stream' => true,
            ]);
        } catch (ConnectionException|OutboundRequestBlocked) {
            $this->failWith('The MCP destination was blocked or could not be reached.');
        }

        $this->captureSessionId($response);

        if ($response->status() === 401 || $response->status() === 403) {
            $challenge = WwwAuthenticateChallenge::parse($response->header('WWW-Authenticate'));
            $this->reset();

            throw new AuthorizationRequiredException(
                "The MCP server responded with HTTP {$response->status()}. Authorization is required.",
                $challenge,
            );
        }

        if ($response->notFound() && $hadSession) {
            $this->reset();

            throw new SessionExpiredException('The MCP session expired.');
        }

        if (! $response->successful()) {
            $this->failWith("The MCP server returned unexpected HTTP status [{$response->status()}].");
        }

        $this->initialized = true;

        if (str_contains($response->header('Content-Type'), 'text/event-stream')) {
            $this->readSseStream($response);

            return;
        }

        $body = trim($response->body());

        if ($response->accepted() || $body === '') {
            return;
        }

        $this->queue[] = $body;
    }

    protected function terminateSession(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        try {
            $this->http->send('mcp', 'DELETE', $this->url, [
                'headers' => $this->headers(),
                'timeout' => $this->timeoutSeconds,
                'connect_timeout' => $this->connectTimeoutSeconds,
                'max_redirects' => 0,
            ]);
        } catch (Throwable) {
            // Session termination is best effort during disconnect/destruction.
        }
    }
}
