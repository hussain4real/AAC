<?php

namespace App\Models;

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Support\Platform\PlatformAccessManager;
use Database\Factories\PlatformAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The audited ledger of a MAAC platform-role grant (Phase 8B).
 *
 * Spatie's `model_has_roles` is the authorization source of truth; this record
 * is the governance trail around it — who granted which platform role to whom,
 * why, whether it is a time-boxed break-glass grant ({@see $expires_at}), when
 * it was last re-certified ({@see $certified_at}), and when it was revoked. The
 * {@see PlatformAccessManager} keeps the two in sync.
 *
 * @property string $id
 * @property int $user_id
 * @property PlatformRole $role
 * @property PlatformAccessKind $kind
 * @property string|null $reason
 * @property int|null $granted_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $certified_at
 * @property int|null $certified_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $grantedBy
 */
#[Fillable(['user_id', 'role', 'kind', 'reason', 'granted_by', 'expires_at', 'certified_at', 'certified_by', 'revoked_at', 'revoked_by'])]
class PlatformAccessGrant extends Model
{
    /** @use HasFactory<PlatformAccessGrantFactory> */
    use HasFactory, HasUuids;

    /**
     * The user the platform role is granted to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The platform admin who made the grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * The platform admin who last certified the grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    /**
     * The platform admin who revoked the grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Limit to grants still in force (not revoked and not expired).
     *
     * @param  Builder<PlatformAccessGrant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Limit to break-glass grants whose window has elapsed but are not yet
     * revoked (the access-review cleanup target).
     *
     * @param  Builder<PlatformAccessGrant>  $query
     */
    public function scopeDueForExpiry(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Whether the grant is currently in force.
     */
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Whether this is a break-glass grant.
     */
    public function isBreakGlass(): bool
    {
        return $this->kind === PlatformAccessKind::BreakGlass;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'kind' => PlatformAccessKind::class,
            'expires_at' => 'datetime',
            'certified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
