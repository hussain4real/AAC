<?php

declare(strict_types=1);

namespace Maacc\Sdk\Exceptions;

use RuntimeException;

/**
 * Base class for every exception thrown by the MAACC SDK, so consumers can catch
 * the whole family with a single `catch (MaaccException $e)`.
 */
class MaaccException extends RuntimeException {}
