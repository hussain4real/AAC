<?php

namespace Tests\Support;

use App\Models\SsoConnection;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OidcTestProvider
{
    /**
     * @var array{private: string, jwk: array<string, string>}|null
     */
    private static ?array $keyPair = null;

    /**
     * @param  array<string, mixed>  $userinfo
     * @param  array<string, mixed>  $claimOverrides
     */
    public static function fake(SsoConnection $connection, array $userinfo, int $tokenStatus = 200, int $userinfoStatus = 200, array $claimOverrides = []): void
    {
        $fixture = self::signedFixture($connection, $userinfo, $claimOverrides);

        Http::preventStrayRequests();
        Http::fake([
            $connection->token_url => Http::response(['access_token' => 'at-123', 'id_token' => $fixture['id_token'], 'token_type' => 'Bearer'], $tokenStatus),
            $connection->userinfo_url => Http::response($userinfo, $userinfoStatus),
            $connection->jwks_url => Http::response($fixture['key_set']),
        ]);
    }

    /**
     * Build a signed token and its public key set for custom HTTP-failure fixtures.
     *
     * @param  array<string, mixed>  $userinfo
     * @param  array<string, mixed>  $claimOverrides
     * @return array{id_token: string, key_set: array{keys: array<int, array<string, string>>}}
     */
    public static function signedFixture(SsoConnection $connection, array $userinfo, array $claimOverrides = []): array
    {
        $keys = self::keys();
        $claims = [
            ...$userinfo,
            'iss' => $connection->issuer,
            'aud' => $connection->client_id,
            'azp' => $connection->client_id,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'nonce' => 'nonce-token',
            'email_verified' => true,
            ...$claimOverrides,
        ];

        return [
            'id_token' => JWT::encode($claims, $keys['private'], 'RS256', 'test-key'),
            'key_set' => ['keys' => [$keys['jwk']]],
        ];
    }

    /**
     * @return array{sso: array{flows: array<string, array{state: string, connection_id: string, nonce: string, code_verifier: string, created_at: int}>}}
     */
    public static function session(SsoConnection $connection, string $state = 'state-token'): array
    {
        return ['sso' => ['flows' => [$state => [
            'state' => $state,
            'connection_id' => $connection->id,
            'nonce' => 'nonce-token',
            'code_verifier' => 'code-verifier',
            'created_at' => now()->timestamp,
        ]]]];
    }

    /**
     * @return array{private: string, jwk: array<string, string>}
     */
    private static function keys(): array
    {
        return self::$keyPair ??= self::generateKeys();
    }

    /**
     * @return array{private: string, jwk: array<string, string>}
     */
    private static function generateKeys(): array
    {
        // Some sandboxed test runners cannot persist OpenSSL's optional random
        // state file even though the OS-backed key generation succeeds.
        $key = @openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKey = null;
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if (
            $key === false
            || ! openssl_pkey_export($key, $privateKey)
            || ! is_string($privateKey)
            || $privateKey === ''
            || ! is_array($details)
            || ! is_string($details['rsa']['n'] ?? null)
            || ! is_string($details['rsa']['e'] ?? null)
        ) {
            throw new RuntimeException('Unable to generate OIDC test RSA keypair.');
        }

        return [
            'private' => $privateKey,
            'jwk' => [
                'kty' => 'RSA',
                'use' => 'sig',
                'kid' => 'test-key',
                'alg' => 'RS256',
                'n' => self::base64UrlEncode($details['rsa']['n']),
                'e' => self::base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
