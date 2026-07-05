<?php

namespace App\Support\Sso;

use App\Models\SsoConnection;
use Illuminate\Support\Facades\Http;

/**
 * Drives the OAuth 2.0 / OIDC authorization-code flow against a connection's
 * configured endpoints over the HTTP client: it builds the authorize URL, then
 * exchanges the returned code for an access token and fetches the userinfo
 * claims, normalizing them into an {@see SsoIdentityPayload}. Every outbound call
 * goes through Laravel's HTTP client, so the whole flow is `Http::fake`-able.
 */
class SsoAuthenticator
{
    /**
     * Build the provider authorize URL the user is redirected to.
     */
    public function authorizeUrl(SsoConnection $connection, string $state, string $nonce): string
    {
        return $connection->authorize_url.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $connection->client_id,
            'redirect_uri' => $this->redirectUri($connection),
            'scope' => implode(' ', $connection->scopeList()),
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Exchange an authorization code for tokens and resolve the userinfo claims.
     *
     * @throws SsoException
     */
    public function exchange(SsoConnection $connection, string $code): SsoIdentityPayload
    {
        $timeout = (int) config('maacc.sso.http_timeout_seconds');

        $token = Http::asForm()->timeout($timeout)->post($connection->token_url, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($connection),
            'client_id' => $connection->client_id,
            'client_secret' => (string) $connection->client_secret,
        ]);

        if ($token->failed()) {
            throw new SsoException('the token exchange was rejected by the provider');
        }

        $accessToken = $token->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new SsoException('the provider did not return an access token');
        }

        $userinfo = Http::withToken($accessToken)->timeout($timeout)->get($connection->userinfo_url);

        if ($userinfo->failed()) {
            throw new SsoException('the user profile could not be retrieved');
        }

        $claims = $userinfo->json();

        if (! is_array($claims)) {
            throw new SsoException('the provider returned an invalid user profile');
        }

        return $this->payload($connection, $claims);
    }

    /**
     * The redirect URI MAACC registered with the provider for this connection.
     */
    public function redirectUri(SsoConnection $connection): string
    {
        return route('sso.callback', ['ssoConnection' => $connection->slug]);
    }

    /**
     * Normalize the provider claims into an identity payload using the
     * connection's claim mapping.
     *
     * @param  array<string, mixed>  $claims
     *
     * @throws SsoException
     */
    private function payload(SsoConnection $connection, array $claims): SsoIdentityPayload
    {
        $subject = $claims['sub'] ?? null;
        $email = $claims[$connection->email_claim] ?? null;

        if (! is_string($subject) || $subject === '' || ! is_string($email) || $email === '') {
            throw new SsoException('the provider did not return the required identity claims');
        }

        $name = $claims[$connection->name_claim] ?? null;
        $groupsRaw = $claims[$connection->groups_claim] ?? [];

        return new SsoIdentityPayload(
            subject: $subject,
            email: $email,
            name: is_string($name) && $name !== '' ? $name : $email,
            groups: is_array($groupsRaw) ? array_values(array_filter($groupsRaw, 'is_string')) : [],
            rawClaims: $claims,
        );
    }
}
