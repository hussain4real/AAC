<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use Illuminate\Support\Carbon;

class RevokeCredential
{
    /**
     * Revoke a credential.
     */
    public function handle(Credential $credential): Credential
    {
        $credential->update([
            'status' => CredentialStatus::Revoked->value,
            'revoked_at' => Carbon::now(),
        ]);

        return $credential;
    }
}
