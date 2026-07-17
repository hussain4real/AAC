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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $user
 */
#[Fillable(['project_id', 'user_id', 'maacc_role'])]
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
}
