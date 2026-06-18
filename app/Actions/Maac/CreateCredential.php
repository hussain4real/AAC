<?php

namespace App\Actions\Maac;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Credential;
use App\Models\User;
use App\Support\Sdk\SdkClientManager;

class CreateCredential
{
    public function __construct(private readonly SdkClientManager $sdkClients) {}

    /**
     * Generate an application credential and return its one-time plaintext secret.
     *
     * The credential is backed by a Passport client_credentials client so the
     * application can exchange the client id/secret for short-lived SDK access
     * tokens at MAAC's `/oauth/token` endpoint.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Application $application, User $creator, array $data): CredentialSecret
    {
        $environment = Environment::from((string) $data['environment']);
        $label = (string) ($data['label'] ?? '');
        $label = $label !== '' ? $label : $environment->label().' credentials';

        $credential = new Credential([
            'application_id' => $application->id,
            'environment' => $environment->value,
            'label' => $label,
            'status' => CredentialStatus::Active->value,
            'created_by' => $creator->id,
        ]);

        $plainSecret = $this->sdkClients->provision(
            $credential,
            $application->name.' — '.$environment->label(),
        );
        $credential->save();

        return new CredentialSecret($credential, $plainSecret);
    }
}
