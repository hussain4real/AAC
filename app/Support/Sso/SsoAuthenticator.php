<?php

namespace App\Support\Sso;

use App\Enums\SsoFailureCode;
use App\Models\SsoConnection;
use Illuminate\Http\Client\ConnectionException;
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
    public function __construct(private readonly OidcTokenValidator $tokenValidator) {}

    /**
     * Build the provider authorize URL the user is redirected to.
     */
    public function authorizeUrl(SsoConnection $connection, string $state, string $nonce, string $codeChallenge): string
    {
        return $connection->authorize_url.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $connection->client_id,
            'redirect_uri' => $this->redirectUri($connection),
            'scope' => implode(' ', $connection->scopeList()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Exchange an authorization code for tokens and resolve the userinfo claims.
     *
     * @throws SsoException
     */
    public function exchange(SsoConnection $connection, string $code, string $codeVerifier, string $expectedNonce): SsoIdentityPayload
    {
        $timeout = (int) config('maacc.sso.http_timeout_seconds');

        try {
            $token = Http::asForm()
                ->connectTimeout((int) config('maacc.sso.connect_timeout_seconds', 3))
                ->timeout($timeout)
                ->post($connection->token_url, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri($connection),
                    'client_id' => $connection->client_id,
                    'client_secret' => (string) $connection->client_secret,
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException) {
            throw new SsoException('the identity provider could not be reached', SsoFailureCode::IdpUnavailable);
        }

        if ($token->failed()) {
            throw new SsoException('the token exchange was rejected by the provider', SsoFailureCode::TokenExchangeRejected);
        }

        $accessToken = $token->json('access_token');
        $idToken = $token->json('id_token');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($idToken) || $idToken === '') {
            throw new SsoException('the provider did not return the required OIDC tokens', SsoFailureCode::RequiredTokensMissing);
        }

        $idTokenClaims = $this->tokenValidator->validate($connection, $idToken, $expectedNonce);

        try {
            $userinfo = Http::withToken($accessToken)
                ->connectTimeout((int) config('maacc.sso.connect_timeout_seconds', 3))
                ->timeout($timeout)
                ->get($connection->userinfo_url);
        } catch (ConnectionException) {
            throw new SsoException('the identity provider user profile could not be reached', SsoFailureCode::IdpUnavailable);
        }

        if ($userinfo->failed()) {
            throw new SsoException('the user profile could not be retrieved', SsoFailureCode::UserinfoRejected);
        }

        $claims = $userinfo->json();

        if (! is_array($claims)) {
            throw new SsoException('the provider returned an invalid user profile', SsoFailureCode::InvalidUserinfo);
        }

        if (($claims['sub'] ?? null) !== ($idTokenClaims['sub'] ?? null)) {
            throw new SsoException('the user profile subject did not match the ID token', SsoFailureCode::SubjectMismatch);
        }

        return $this->payload($connection, [...$claims, ...$idTokenClaims]);
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
            throw new SsoException('the provider did not return the required identity claims', SsoFailureCode::IdentityClaimsMissing);
        }

        if (($claims['email_verified'] ?? null) !== true) {
            throw new SsoException('the identity provider did not verify the email address', SsoFailureCode::EmailUnverified);
        }

        $domain = strtolower((string) strrchr($email, '@'));
        $allowedDomains = array_map(
            static fn (string $allowed): string => '@'.ltrim(strtolower(trim($allowed)), '@'),
            array_filter($connection->allowed_domains ?? [], 'is_string'),
        );

        if ($connection->auto_provision && ($allowedDomains === [] || ! in_array($domain, $allowedDomains, true))) {
            throw new SsoException('the email domain is not approved for this tenant', SsoFailureCode::DomainNotApproved);
        }

        $name = $claims[$connection->name_claim] ?? null;
        $groupsRaw = $claims[$connection->groups_claim] ?? [];

        return new SsoIdentityPayload(
            subject: $subject,
            email: $email,
            name: is_string($name) && $name !== '' ? $name : $email,
            groups: is_array($groupsRaw) ? array_values(array_filter($groupsRaw, 'is_string')) : [],
            rawClaims: [
                'iss' => $claims['iss'] ?? null,
                'sub' => $subject,
                'email' => $email,
                'email_verified' => true,
                'groups' => is_array($groupsRaw) ? array_values(array_filter($groupsRaw, 'is_string')) : [],
            ],
            issuer: (string) $connection->issuer,
        );
    }
}
