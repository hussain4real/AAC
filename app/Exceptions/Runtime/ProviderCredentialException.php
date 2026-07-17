<?php

namespace App\Exceptions\Runtime;

use RuntimeException;

/**
 * Raised when a tenant-owned model has no vault-bound credential.
 */
class ProviderCredentialException extends RuntimeException {}
