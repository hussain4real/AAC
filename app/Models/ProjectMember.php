<?php

namespace App\Models;

use App\Enums\MaaccRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $project_id
 * @property int $user_id
 * @property MaaccRole|null $maacc_role
 * @property int|null $granted_by_user_id
 * @property int|null $revoked_by_user_id
 * @property int|null $certified_by_user_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $certified_at
 * @property string|null $reason
 * @property string|null $certification_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $user
 * @property-read User|null $grantor
 * @property-read User|null $revoker
 * @property-read User|null $certifier
 */
#[Fillable(['project_id', 'user_id', 'maacc_role', 'granted_by_user_id', 'revoked_by_user_id', 'certified_by_user_id', 'expires_at', 'revoked_at', 'certified_at', 'reason', 'certification_note'])]
class ProjectMember extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the project the membership belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user the membership belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the actor who granted the current access term.
     *
     * @return BelongsTo<User, $this>
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * Get the actor who revoked the access term.
     *
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * Get the actor who most recently certified the access term.
     *
     * @return BelongsTo<User, $this>
     */
    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by_user_id');
    }

    /**
     * Determine whether this access term is currently effective.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Fail closed when a legacy import contains a null or unknown role value.
     *
     * @return Attribute<MaaccRole|null, MaaccRole|string|null>
     */
    protected function maaccRole(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?MaaccRole => $value instanceof MaaccRole
                ? $value
                : (is_string($value) ? MaaccRole::tryFrom($value) : null),
            set: fn (MaaccRole|string|null $value): ?string => $value instanceof MaaccRole ? $value->value : $value,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'certified_at' => 'datetime',
        ];
    }
}
