<?php

namespace App\Actions\Maac;

use App\Models\Credential;

final readonly class CredentialSecret
{
    /**
     * Create a new credential secret result.
     */
    public function __construct(
        public Credential $credential,
        public string $plainSecret,
    ) {}
}
