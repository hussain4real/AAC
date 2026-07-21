<?php

namespace App\Support\Sso;

use App\Enums\SsoFailureCode;
use App\Exceptions\OutboundRequestBlocked;
use App\Models\SsoConnection;
use App\Support\Outbound\OutboundHttpClient;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class OidcTokenValidator
{
    public function __construct(private readonly OutboundHttpClient $http) {}

    /**
     * Validate a signed OIDC ID token against the connection's pinned trust
     * configuration and return its claims.
     *
     * @return array<string, mixed>
     *
     * @throws SsoException
     */
    public function validate(SsoConnection $connection, string $idToken, string $expectedNonce): array
    {
        $algorithm = $this->algorithm($idToken);
        $allowedAlgorithms = config('maacc.sso.allowed_id_token_algorithms', ['RS256']);

        if (! is_array($allowedAlgorithms) || ! in_array($algorithm, $allowedAlgorithms, true)) {
            throw new SsoException('the provider used an unapproved ID token algorithm', SsoFailureCode::InvalidAlgorithm);
        }

        $jwksUrl = $connection->jwks_url;

        if (! is_string($jwksUrl) || $jwksUrl === '') {
            throw new SsoException('the connection has no pinned JWKS endpoint', SsoFailureCode::JwksUnavailable);
        }

        try {
            $timeout = (int) config('maacc.sso.http_timeout_seconds', 10);
            $connectTimeout = (int) config('maacc.sso.connect_timeout_seconds', 3);
            $response = $this->http->send('sso', 'GET', $jwksUrl, [
                'headers' => ['Accept' => 'application/json'],
                'connect_timeout' => $connectTimeout,
                'timeout' => $timeout,
                'max_redirects' => 2,
            ]);
        } catch (ConnectionException|OutboundRequestBlocked) {
            throw new SsoException('the provider signing keys could not be retrieved', SsoFailureCode::JwksUnavailable);
        }

        if (! $response->successful()) {
            throw new SsoException('the provider signing keys could not be retrieved', SsoFailureCode::JwksUnavailable);
        }

        $keySet = $response->json();

        if (! is_array($keySet) || ! is_array($keySet['keys'] ?? null) || $keySet['keys'] === []) {
            throw new SsoException('the provider returned an invalid signing key set', SsoFailureCode::InvalidSigningKeys);
        }

        try {
            $decoded = JWT::decode($idToken, JWK::parseKeySet($keySet, $algorithm));
            $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new SsoException('the provider returned an invalid or unsigned ID token', SsoFailureCode::InvalidOrUnsignedToken);
        }

        $this->validateClaims($connection, $claims, $expectedNonce);

        return $claims;
    }

    /**
     * @throws SsoException
     */
    public function testKeySet(SsoConnection $connection): void
    {
        $jwksUrl = $connection->jwks_url;

        if (! is_string($jwksUrl) || $jwksUrl === '') {
            throw new SsoException('the connection has no pinned JWKS endpoint', SsoFailureCode::JwksUnavailable);
        }

        try {
            $response = $this->http->send('sso', 'GET', $jwksUrl, [
                'headers' => ['Accept' => 'application/json'],
                'connect_timeout' => (int) config('maacc.sso.connect_timeout_seconds', 3),
                'timeout' => (int) config('maacc.sso.http_timeout_seconds', 10),
                'max_redirects' => 2,
            ]);
        } catch (ConnectionException|OutboundRequestBlocked) {
            throw new SsoException('the provider signing keys could not be retrieved', SsoFailureCode::JwksUnavailable);
        }

        $keys = $response->json('keys');

        if (! $response->successful() || ! is_array($keys) || $keys === []) {
            throw new SsoException('the provider returned no usable signing keys', SsoFailureCode::InvalidSigningKeys);
        }
    }

    /**
     * Read the untrusted header only to select an explicitly allowlisted
     * verification algorithm. No claim is trusted until signature validation.
     *
     * @throws SsoException
     */
    private function algorithm(string $idToken): string
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw new SsoException('the provider returned a malformed ID token', SsoFailureCode::InvalidOrUnsignedToken);
        }

        $headerJson = JWT::urlsafeB64Decode($segments[0]);
        $header = json_decode($headerJson, true);
        $algorithm = is_array($header) ? ($header['alg'] ?? null) : null;

        if (! is_string($algorithm) || $algorithm === '' || strtolower($algorithm) === 'none') {
            throw new SsoException('the provider returned an unsigned ID token', SsoFailureCode::InvalidOrUnsignedToken);
        }

        return $algorithm;
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws SsoException
     */
    private function validateClaims(SsoConnection $connection, array $claims, string $expectedNonce): void
    {
        if (! is_string($connection->issuer) || $connection->issuer === '' || ($claims['iss'] ?? null) !== $connection->issuer) {
            throw new SsoException('the ID token issuer did not match the connection', SsoFailureCode::IssuerMismatch);
        }

        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];

        if (! in_array($connection->client_id, $audiences, true)) {
            throw new SsoException('the ID token audience did not match this application', SsoFailureCode::AudienceMismatch);
        }

        if ((count($audiences) > 1 || array_key_exists('azp', $claims)) && ($claims['azp'] ?? null) !== $connection->client_id) {
            throw new SsoException('the ID token authorized party did not match this application', SsoFailureCode::AuthorizedPartyMismatch);
        }

        if (! isset($claims['exp'], $claims['iat']) || ! is_numeric($claims['exp']) || ! is_numeric($claims['iat'])) {
            throw new SsoException('the ID token omitted required lifetime claims', SsoFailureCode::LifetimeInvalid);
        }

        $nonce = $claims['nonce'] ?? null;

        if (! is_string($nonce) || ! hash_equals($expectedNonce, $nonce)) {
            throw new SsoException('the ID token nonce did not match the login request', SsoFailureCode::NonceMismatch);
        }
    }
}
