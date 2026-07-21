<?php

namespace App\Models;

use Database\Factories\SsoIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's external identity from an SSO connection, keyed by the provider's
 * stable subject claim. Recording it lets MAACC recognize a returning user and
 * lets a security reviewer trace which external identity a user signed in with.
 *
 * @property string $id
 * @property string $sso_connection_id
 * @property int $user_id
 * @property string $subject
 * @property string|null $email
 * @property array<string, mixed>|null $raw_claims
 * @property array<int, string>|null $managed_project_ids
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SsoConnection $connection
 * @property-read User $user
 */
#[Fillable(['sso_connection_id', 'user_id', 'subject', 'email', 'raw_claims', 'managed_project_ids', 'last_login_at'])]
class SsoIdentity extends Model
{
    /** @use HasFactory<SsoIdentityFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the connection the identity was authenticated through.
     *
     * @return BelongsTo<SsoConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(SsoConnection::class, 'sso_connection_id');
    }

    /**
     * Get the local user the identity is linked to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_claims' => 'array',
            'managed_project_ids' => 'array',
            'last_login_at' => 'datetime',
        ];
    }
}
