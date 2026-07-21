<?php

namespace App\Support\Sso;

/**
 * The normalized external identity resolved from an SSO provider's userinfo
 * response: the stable subject, the mapped email/name, the groups used for role
 * mapping, and the raw claims (kept for the audit trail).
 */
final readonly class SsoIdentityPayload
{
    /**
     * @param  array<int, string>  $groups
     * @param  array<string, mixed>  $rawClaims
     */
    public function __construct(
        public string $subject,
        public string $email,
        public string $name,
        public array $groups,
        public array $rawClaims,
        public string $issuer = '',
    ) {}
}
