<?php

declare(strict_types=1);

namespace Maacc\Sdk\Contracts;

use Maacc\Sdk\Exceptions\TransportException;
use Maacc\Sdk\Http\CurlTransport;
use Maacc\Sdk\Http\HttpRequest;
use Maacc\Sdk\Http\HttpResponse;

/**
 * Abstracts the HTTP round-trip the SDK depends on so the client is decoupled
 * from any particular transport. The shipped {@see CurlTransport}
 * speaks to a live MAACC instance over the network, while consuming applications
 * (and MAACC's own test suite) can supply an in-process or instrumented
 * implementation without changing a line of client code.
 */
interface Transport
{
    /**
     * Perform the request and return MAACC's raw response.
     *
     * Implementations must NOT throw on a non-2xx status — the SDK inspects the
     * status and decodes MAACC's controlled error envelope itself. They should
     * only throw a {@see TransportException} when the round
     * trip genuinely could not complete (DNS failure, timeout, refused socket).
     */
    public function send(HttpRequest $request): HttpResponse;
}
