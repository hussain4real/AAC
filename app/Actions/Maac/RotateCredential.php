<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use App\Support\Sdk\SdkClientManager;
use Illuminate\Support\Carbon;

class RotateCredential
{
    public function __construct(private readonly SdkClientManager $sdkClients) {}

    /**
     * Rotate a credential secret while preserving its client identity. The
     * backing Passport client's secret is regenerated so previously issued
     * SDK tokens can no longer be re-minted with the old secret.
     */
    public function handle(Credential $credential): CredentialSecret
    {
        $plainSecret = $this->sdkClients->rotate($credential);
        $credential->status = CredentialStatus::Active;
        $credential->rotated_at = Carbon::now();
        $credential->revoked_at = null;
        $credential->save();

        return new CredentialSecret($credential, $plainSecret);
    }
}
