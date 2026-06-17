<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Credential;
use App\Models\User;

class CreateCredential
{
    /**
     * Generate an application credential and return its one-time plaintext secret.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Application $application, User $creator, array $data): CredentialSecret
    {
        $environment = Environment::from((string) $data['environment']);
        $label = (string) ($data['label'] ?? '');
        $plainSecret = Credential::generateSecret();

        $credential = new Credential([
            'application_id' => $application->id,
            'environment' => $environment->value,
            'label' => $label !== '' ? $label : $environment->label().' credentials',
            'client_id' => Credential::generateClientId(),
            'status' => CredentialStatus::Active->value,
            'created_by' => $creator->id,
        ]);

        $credential->fillSecret($plainSecret);
        $credential->save();

        return new CredentialSecret($credential, $plainSecret);
    }
}
