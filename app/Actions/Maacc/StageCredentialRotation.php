<?php

namespace App\Actions\Maacc;

use App\Models\Credential;

class StageCredentialRotation
{
    /**
     * Generate a one-time candidate secret without changing the live client.
     */
    public function handle(Credential $credential): CredentialSecret
    {
        return new CredentialSecret($credential, Credential::generateSecret());
    }
}
