<?php

declare(strict_types=1);

namespace Maac\Sdk\Exceptions;

/**
 * Thrown when an HTTP round-trip to MAAC could not complete at all (connection
 * refused, DNS failure, timeout) or returned an undecodable body. This is
 * distinct from {@see MaacApiException}, which represents a controlled error
 * response MAAC deliberately returned.
 */
class TransportException extends MaacException {}
