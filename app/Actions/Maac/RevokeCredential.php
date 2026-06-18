<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Support\Sdk\SdkClientManager;
use Illuminate\Support\Carbon;

class RevokeCredential
{
    public function __construct(private readonly SdkClientManager $sdkClients) {}

    /**
     * Revoke a credential and the SDK tokens issued through its backing
     * Passport client, so it can no longer authenticate.
     */
    public function handle(Credential $credential): Credential
    {
        $this->sdkClients->revoke($credential);

        $credential->update([
            'status' => CredentialStatus::Revoked->value,
            'revoked_at' => Carbon::now(),
        ]);

        return $credential;
    }
}
