<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\McpConnectorStatus;
use App\Enums\RemoteAuthType;
use App\Enums\Sensitivity;
use Database\Factories\McpConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A registered external MCP server MAAC connects to as a client. Tool contracts
 * with execution_mode = connector reference a connector and a remote tool name;
 * the runtime discovers the connector's capabilities and invokes the mapped tool
 * through the Laravel MCP client.
 *
 * @property string $id
 * @property int $team_id
 * @property string|null $application_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $transport
 * @property string $server_url
 * @property RemoteAuthType $auth_type
 * @property string|null $auth_credential
 * @property string|null $auth_header
 * @property Sensitivity $sensitivity
 * @property bool $requires_approval
 * @property McpConnectorStatus $status
 * @property array<int, string> $environments
 * @property array<int, array<string, mixed>>|null $capabilities
 * @property int $timeout_seconds
 * @property Carbon|null $last_discovered_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Application|null $application
 * @property-read User|null $creator
 * @property-read Collection<int, ToolContract> $tools
 */
#[Fillable(['team_id', 'application_id', 'slug', 'name', 'description', 'transport', 'server_url', 'auth_type', 'auth_credential', 'auth_header', 'sensitivity', 'requires_approval', 'status', 'environments', 'capabilities', 'timeout_seconds', 'last_discovered_at', 'created_by'])]
#[Hidden(['auth_credential'])]
class McpConnector extends Model
{
    /** @use HasFactory<McpConnectorFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the connector.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the application that owns the connector (null for platform connectors).
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user that registered the connector.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tool contracts backed by this connector.
     *
     * @return HasMany<ToolContract, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(ToolContract::class);
    }

    /**
     * Whether the connector may be invoked by the runtime.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the connector is active and available in the given environment.
     */
    public function isAvailableIn(string $environment): bool
    {
        return $this->isActive() && in_array($environment, $this->environments, true);
    }

    /**
     * The remote tool names discovered from the connector's last capability sync.
     *
     * @return array<int, string>
     */
    public function discoveredToolNames(): array
    {
        return collect($this->capabilities ?? [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this connector is audited under.
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
            'auth_type' => RemoteAuthType::class,
            'auth_credential' => 'encrypted',
            'sensitivity' => Sensitivity::class,
            'requires_approval' => 'boolean',
            'status' => McpConnectorStatus::class,
            'environments' => 'array',
            'capabilities' => 'array',
            'timeout_seconds' => 'integer',
            'last_discovered_at' => 'datetime',
        ];
    }
}
