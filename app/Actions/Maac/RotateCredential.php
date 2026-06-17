<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use Illuminate\Support\Carbon;

class RotateCredential
{
    /**
     * Rotate a credential secret while preserving its client identity.
     */
    public function handle(Credential $credential): CredentialSecret
    {
        $plainSecret = Credential::generateSecret();
        $credential->fillSecret($plainSecret);
        $credential->status = CredentialStatus::Active;
        $credential->rotated_at = Carbon::now();
        $credential->revoked_at = null;
        $credential->save();

        return new CredentialSecret($credential, $plainSecret);
    }
}
