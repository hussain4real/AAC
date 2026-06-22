<?php

declare(strict_types=1);

namespace Maac\Sdk\Contracts;

use Maac\Sdk\Exceptions\TransportException;
use Maac\Sdk\Http\CurlTransport;
use Maac\Sdk\Http\HttpRequest;
use Maac\Sdk\Http\HttpResponse;

/**
 * Abstracts the HTTP round-trip the SDK depends on so the client is decoupled
 * from any particular transport. The shipped {@see CurlTransport}
 * speaks to a live MAAC instance over the network, while consuming applications
 * (and MAAC's own test suite) can supply an in-process or instrumented
 * implementation without changing a line of client code.
 */
interface Transport
{
    /**
     * Perform the request and return MAAC's raw response.
     *
     * Implementations must NOT throw on a non-2xx status — the SDK inspects the
     * status and decodes MAAC's controlled error envelope itself. They should
     * only throw a {@see TransportException} when the round
     * trip genuinely could not complete (DNS failure, timeout, refused socket).
     */
    public function send(HttpRequest $request): HttpResponse;
}
