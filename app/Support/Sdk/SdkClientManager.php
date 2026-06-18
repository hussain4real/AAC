<?php

namespace App\Support\Sdk;

use App\Models\Credential;
use Laravel\Passport\ClientRepository;

/**
 * Manages the Passport client_credentials client that backs a MAAC credential
 * for SDK/API token issuance.
 *
 * Each credential maps 1:1 to a Passport client: MAAC stores its own bcrypt
 * hash of the plaintext for display metadata, while Passport owns the secret
 * used to validate `client_credentials` token requests at `/oauth/token`.
 */
class SdkClientManager
{
    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * Provision a fresh Passport client for the credential, stamping its
     * client/secret identity onto the (unsaved) credential. Returns the one-time
     * plaintext secret to display.
     */
    public function provision(Credential $credential, string $name): string
    {
        $client = $this->clients->createClientCredentialsGrantClient($name);
        $plainSecret = (string) $client->plainSecret;

        $credential->forceFill([
            'client_id' => $client->getKey(),
            'oauth_client_id' => $client->getKey(),
        ]);
        $credential->fillSecret($plainSecret);

        return $plainSecret;
    }

    /**
     * Rotate the credential's secret, regenerating the backing Passport client's
     * secret when one exists. Returns the new one-time plaintext secret.
     */
    public function rotate(Credential $credential): string
    {
        $client = $credential->oauthClient;

        if ($client === null) {
            $plainSecret = Credential::generateSecret();
            $credential->fillSecret($plainSecret);

            return $plainSecret;
        }

        $this->clients->regenerateSecret($client);
        $plainSecret = (string) $client->plainSecret;
        $credential->fillSecret($plainSecret);

        return $plainSecret;
    }

    /**
     * Revoke the backing Passport client and all of its issued tokens.
     */
    public function revoke(Credential $credential): void
    {
        $client = $credential->oauthClient;

        if ($client === null) {
            return;
        }

        $client->tokens()->update(['revoked' => true]);
        $client->forceFill(['revoked' => true])->save();
    }
}
