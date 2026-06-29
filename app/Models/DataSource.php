<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\DataSourceStatus;
use App\Enums\DbConnectionType;
use App\Enums\Sensitivity;
use App\Support\Secrets\Contracts\SecretVault;
use Database\Factories\DataSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A governed read-only data source MAAC may query for a `db` tool contract. The
 * runtime only queries an active source available in the run's environment,
 * through the ops-provisioned read-only connection it references; any credential
 * MAAC must inject is resolved from the secrets vault at query time, so MAAC
 * never persists a plaintext connection string, username, or password. A
 * sensitive source is gated behind a data-source access approval and stays a
 * draft until granted.
 *
 * @property string $id
 * @property int $team_id
 * @property string|null $application_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property DbConnectionType $connection_type
 * @property string $connection
 * @property string|null $driver
 * @property string|null $vault_secret_id
 * @property DataSourceStatus $status
 * @property Sensitivity $sensitivity
 * @property bool $requires_approval
 * @property array<int, string> $environments
 * @property array<int, string> $allowed_relations
 * @property int $max_rows
 * @property int $statement_timeout_ms
 * @property int $max_result_kb
 * @property Carbon|null $data_refreshed_at
 * @property int|null $staleness_threshold_minutes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Application|null $application
 * @property-read User|null $creator
 * @property-read VaultSecret|null $vaultSecret
 * @property-read Collection<int, ToolContract> $tools
 */
#[Fillable(['team_id', 'application_id', 'slug', 'name', 'description', 'connection_type', 'connection', 'driver', 'vault_secret_id', 'status', 'sensitivity', 'requires_approval', 'environments', 'allowed_relations', 'max_rows', 'statement_timeout_ms', 'max_result_kb', 'data_refreshed_at', 'staleness_threshold_minutes', 'created_by'])]
class DataSource extends Model
{
    /** @use HasFactory<DataSourceFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the data source.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the application that owns the source (null for platform sources).
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user that registered the source.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the vault secret holding the source's connection credential, if any.
     *
     * @return BelongsTo<VaultSecret, $this>
     */
    public function vaultSecret(): BelongsTo
    {
        return $this->belongsTo(VaultSecret::class, 'vault_secret_id');
    }

    /**
     * Get the tool contracts that query this source.
     *
     * @return HasMany<ToolContract, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(ToolContract::class);
    }

    /**
     * Whether the source may be queried by the runtime.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the source is active and available in the given environment.
     */
    public function isAvailableIn(string $environment): bool
    {
        return $this->isActive() && in_array($environment, $this->environments, true);
    }

    /**
     * The allowlisted relations (views/tables) a `db` tool query may reference.
     *
     * @return array<int, string>
     */
    public function allowedRelations(): array
    {
        $relations = [];

        foreach ($this->allowed_relations as $relation) {
            if (trim($relation) !== '') {
                $relations[] = trim($relation);
            }
        }

        return $relations;
    }

    /**
     * Resolve the source's connection credential from the secrets vault, or null
     * when the connection needs no MAAC-injected credential.
     */
    public function resolveCredential(SecretVault $vault): ?string
    {
        $secret = $this->vaultSecret;

        return $secret instanceof VaultSecret ? $vault->read($secret) : null;
    }

    /**
     * Whether the source's last-refreshed time is older than the requested
     * maximum age, indicating a potentially stale replica. A null marker or a
     * null/zero age means freshness is not enforced.
     */
    public function isStale(?int $maxAgeMinutes): bool
    {
        $threshold = $maxAgeMinutes ?? $this->staleness_threshold_minutes;

        if ($threshold === null || $threshold <= 0 || $this->data_refreshed_at === null) {
            return false;
        }

        return $this->data_refreshed_at->lt(Date::now()->subMinutes($threshold));
    }

    /**
     * The ephemeral, per-source connection name used when MAAC injects a
     * vault-resolved credential over the referenced base connection.
     */
    public function ephemeralConnectionName(): string
    {
        return 'maac_ds_'.$this->id;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this source is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->team;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_type' => DbConnectionType::class,
            'status' => DataSourceStatus::class,
            'sensitivity' => Sensitivity::class,
            'requires_approval' => 'boolean',
            'environments' => 'array',
            'allowed_relations' => 'array',
            'max_rows' => 'integer',
            'statement_timeout_ms' => 'integer',
            'max_result_kb' => 'integer',
            'data_refreshed_at' => 'datetime',
            'staleness_threshold_minutes' => 'integer',
        ];
    }
}
