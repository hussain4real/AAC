<?php

namespace App\Enums;

enum SsoFailureCode: string
{
    case StateOrConnectionMismatch = 'state_or_connection_mismatch';
    case AuthorizationCodeMissing = 'authorization_code_missing';
    case FlowExpired = 'flow_expired';
    case IdpUnavailable = 'idp_unavailable';
    case JwksUnavailable = 'jwks_unavailable';
    case TokenExchangeRejected = 'token_exchange_rejected';
    case RequiredTokensMissing = 'required_tokens_missing';
    case InvalidAlgorithm = 'invalid_algorithm';
    case InvalidSigningKeys = 'invalid_signing_keys';
    case InvalidOrUnsignedToken = 'invalid_or_unsigned_token';
    case InvalidClaims = 'invalid_claims';
    case IssuerMismatch = 'issuer_mismatch';
    case AudienceMismatch = 'audience_mismatch';
    case AuthorizedPartyMismatch = 'authorized_party_mismatch';
    case LifetimeInvalid = 'lifetime_invalid';
    case NonceMismatch = 'nonce_mismatch';
    case UserinfoRejected = 'userinfo_rejected';
    case InvalidUserinfo = 'invalid_userinfo';
    case SubjectMismatch = 'subject_mismatch';
    case IdentityClaimsMissing = 'identity_claims_missing';
    case EmailUnverified = 'email_unverified';
    case DomainNotApproved = 'domain_not_approved';
    case PlatformAdministratorIdentity = 'platform_administrator_identity';
    case IdentityEmailMismatch = 'identity_email_mismatch';
    case IdentityNotProvisioned = 'identity_not_provisioned';
    case EmailCollision = 'email_collision';
    case LoginRejected = 'login_rejected';

    public function category(): string
    {
        return match ($this) {
            self::IdpUnavailable, self::JwksUnavailable => 'availability',
            self::IdentityNotProvisioned, self::DomainNotApproved => 'policy',
            default => 'security',
        };
    }

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::PlatformAdministratorIdentity,
            self::EmailCollision,
            self::InvalidOrUnsignedToken,
            self::IssuerMismatch,
            self::AudienceMismatch,
            self::AuthorizedPartyMismatch,
            self::NonceMismatch,
            self::StateOrConnectionMismatch => AlertSeverity::High,
            self::AuthorizationCodeMissing,
            self::IdentityNotProvisioned,
            self::DomainNotApproved => AlertSeverity::Low,
            default => AlertSeverity::Medium,
        };
    }

    public function auditAction(): string
    {
        return $this->category() === 'availability'
            ? 'sso.idp_unavailable'
            : 'sso.login_rejected';
    }

    public function triggersImmediateAnomaly(): bool
    {
        return in_array($this, [
            self::PlatformAdministratorIdentity,
            self::EmailCollision,
        ], true);
    }
}
