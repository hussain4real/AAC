<?php

namespace App\Enums;

use App\Models\ApprovalRequest;
use Illuminate\Support\Str;

/**
 * Lifecycle status of a governance {@see ApprovalRequest}.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Determine whether the request is still awaiting a decision.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Determine whether the request has been decided (no longer actionable).
     */
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
