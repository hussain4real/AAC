<?php

declare(strict_types=1);

namespace Maac\Sdk\Exceptions;

use RuntimeException;

/**
 * Base class for every exception thrown by the MAAC SDK, so consumers can catch
 * the whole family with a single `catch (MaacException $e)`.
 */
class MaacException extends RuntimeException {}
