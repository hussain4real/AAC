<?php

declare(strict_types=1);

namespace Tests\Support\Sdk;

use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\Http\HttpRequest;
use Maac\Sdk\Http\HttpResponse;
use RuntimeException;

/**
 * A scripted in-memory {@see Transport} for fast, dependency-free SDK unit
 * tests: queue the responses MAAC should return, then assert on the captured
 * requests the SDK made.
 */
final class FakeTransport implements Transport
{
    /**
     * @var array<int, HttpResponse>
     */
    private array $queue = [];

    /**
     * @var array<int, HttpRequest>
     */
    public array $requests = [];

    /**
     * Queue a JSON response.
     *
     * @param  array<string, mixed>  $body
     */
    public function push(int $status, array $body): self
    {
        $this->queue[] = new HttpResponse($status, (string) json_encode($body));

        return $this;
    }

    /**
     * Queue a raw (possibly non-JSON) response body.
     */
    public function pushRaw(int $status, string $body): self
    {
        $this->queue[] = new HttpResponse($status, $body);

        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new RuntimeException("FakeTransport has no queued response for {$request->method} {$request->url}.");
        }

        return array_shift($this->queue);
    }

    /**
     * The request at the given index (0-based, in send order).
     */
    public function request(int $index): HttpRequest
    {
        if (! isset($this->requests[$index])) {
            throw new RuntimeException("No request was made at index {$index}.");
        }

        return $this->requests[$index];
    }
}
