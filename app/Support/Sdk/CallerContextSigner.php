<?php

namespace App\Support\Sdk;

use App\Enums\Environment;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use JsonException;

class CallerContextSigner
{
    /**
     * Issue a short-lived, minimized context for one application/environment.
     *
     * @param  array<string, mixed>  $input
     * @return array{envelope: string, claims: array<string, mixed>}
     */
    public function issue(Application $application, Environment $environment, array $input): array
    {
        $now = Date::now()->getTimestamp();
        $ttl = min(
            max(30, (int) ($input['expires_in'] ?? config('maacc.caller_context.ttl_seconds', 300))),
            300,
        );
        $claims = array_filter([
            'v' => 1,
            'aud' => $application->id,
            'env' => $environment->value,
            'sub' => (string) $input['subject'],
            'department' => is_string($input['department'] ?? null) ? $input['department'] : null,
            'roles' => array_values(array_filter((array) ($input['roles'] ?? []), 'is_string')),
            'iat' => $now,
            'exp' => $now + $ttl,
            'corr' => is_string($input['correlation_id'] ?? null)
                ? $input['correlation_id']
                : 'corr_'.Str::lower((string) Str::ulid()),
            'nonce' => is_string($input['nonce'] ?? null) ? $input['nonce'] : Str::lower((string) Str::ulid()),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
        $payload = $this->encode((string) json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = $this->encode(hash_hmac('sha256', $payload, $this->key(), true));

        return ['envelope' => "{$payload}.{$signature}", 'claims' => $claims];
    }

    /**
     * Verify signature, audience, environment, lifetime, and approved claims.
     *
     * @return array<string, mixed>
     */
    public function verify(string $envelope, Application $application, Environment $environment): array
    {
        [$payload, $signature] = array_pad(explode('.', $envelope, 2), 2, '');
        $expected = $this->encode(hash_hmac('sha256', $payload, $this->key(), true));

        if ($payload === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            throw RuntimeRequestException::invalidCallerContext();
        }

        try {
            $claims = json_decode($this->decode($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw RuntimeRequestException::invalidCallerContext();
        }

        $now = Date::now()->getTimestamp();

        if (! is_array($claims)
            || ($claims['v'] ?? null) !== 1
            || ($claims['aud'] ?? null) !== $application->id
            || ($claims['env'] ?? null) !== $environment->value
            || ! is_string($claims['sub'] ?? null)
            || ! is_int($claims['iat'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now + 30
            || $claims['exp'] < $now
            || $claims['exp'] - $claims['iat'] > 300
            || ! is_string($claims['corr'] ?? null)
            || ! is_string($claims['nonce'] ?? null)
            || ! $this->claimsAreApproved($claims)) {
            throw RuntimeRequestException::invalidCallerContext();
        }

        return array_intersect_key($claims, array_flip([
            'v', 'sub', 'department', 'roles', 'iat', 'exp', 'corr', 'nonce',
        ]));
    }

    /** @param  array<string, mixed>  $claims */
    private function claimsAreApproved(array $claims): bool
    {
        $departments = (array) config('maacc.caller_context.allowed_departments', []);
        $roles = (array) config('maacc.caller_context.allowed_roles', []);
        $department = $claims['department'] ?? null;
        $claimedRoles = $claims['roles'] ?? [];

        return ($department === null || is_string($department) && in_array($department, $departments, true))
            && is_array($claimedRoles)
            && collect($claimedRoles)->every(fn (mixed $role): bool => is_string($role) && in_array($role, $roles, true));
    }

    private function key(): string
    {
        $key = (string) config('maacc.caller_context.signing_key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
