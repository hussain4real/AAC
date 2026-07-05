<?php

declare(strict_types=1);

namespace Maacc\Sdk\Exceptions;

/**
 * Thrown when an HTTP round-trip to MAACC could not complete at all (connection
 * refused, DNS failure, timeout) or returned an undecodable body. This is
 * distinct from {@see MaaccApiException}, which represents a controlled error
 * response MAACC deliberately returned.
 */
class TransportException extends MaaccException {}
