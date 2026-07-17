<?php

namespace App\Enums;

/**
 * Status of an application credential.
 */
enum CredentialStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Revoked = 'revoked';

    /**
     * Get the display label for the status (matches the console contract casing).
     */
    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    /**
     * Determine whether the credential may authenticate.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
