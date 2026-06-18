<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * @property string $id
 * @property string $application_id
 * @property Environment $environment
 * @property string $label
 * @property string $client_id
 * @property string|null $oauth_client_id
 * @property string $secret_hash
 * @property string|null $last_four
 * @property CredentialStatus $status
 * @property Carbon|null $last_used_at
 * @property Carbon|null $rotated_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Application $application
 * @property-read User|null $creator
 * @property-read Client|null $oauthClient
 */
#[Fillable(['application_id', 'environment', 'label', 'client_id', 'oauth_client_id', 'secret_hash', 'last_four', 'status', 'last_used_at', 'rotated_at', 'revoked_at', 'created_by'])]
#[Hidden(['secret_hash'])]
class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /**
     * Get the application the credential belongs to.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user that created the credential.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the Passport client_credentials client that backs this credential for
     * SDK/API token issuance.
     *
     * @return BelongsTo<Client, $this>
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(Passport::clientModel(), 'oauth_client_id');
    }

    /**
     * Generate a new public client identifier.
     */
    public static function generateClientId(): string
    {
        return 'maac_'.Str::lower(Str::random(24));
    }

    /**
     * Generate a new plaintext client secret (shown to the user once).
     */
    public static function generateSecret(): string
    {
        return 'maac_sk_'.Str::random(40);
    }

    /**
     * Hash and store the given plaintext secret, retaining the last four
     * characters for display. The plaintext is never persisted.
     */
    public function fillSecret(string $plainSecret): void
    {
        $this->secret_hash = Hash::make($plainSecret);
        $this->last_four = substr($plainSecret, -4);
    }

    /**
     * Determine whether the credential may still authenticate.
     */
    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }

    /**
     * Resolve the team this credential is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->application->team;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => Environment::class,
            'status' => CredentialStatus::class,
            'last_used_at' => 'datetime',
            'rotated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
