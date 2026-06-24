<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\Sensitivity;
use Database\Factories\KnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A governed knowledge (RAG) source: an approved collection of documents MAAC
 * indexes and retrieves from for a knowledge-mode tool contract. The runtime
 * only retrieves from an active source available in the run's environment; a
 * sensitive source is gated behind an ingestion approval and stays a draft until
 * granted.
 *
 * @property string $id
 * @property int $team_id
 * @property string|null $application_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property KnowledgeSourceStatus $status
 * @property Sensitivity $sensitivity
 * @property bool $requires_approval
 * @property array<int, string> $environments
 * @property int $document_count
 * @property int $chunk_count
 * @property Carbon|null $last_indexed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Application|null $application
 * @property-read User|null $creator
 * @property-read Collection<int, KnowledgeDocument> $documents
 * @property-read Collection<int, KnowledgeChunk> $chunks
 * @property-read Collection<int, ToolContract> $tools
 */
#[Fillable(['team_id', 'application_id', 'slug', 'name', 'description', 'status', 'sensitivity', 'requires_approval', 'environments', 'document_count', 'chunk_count', 'last_indexed_at', 'created_by'])]
class KnowledgeSource extends Model
{
    /** @use HasFactory<KnowledgeSourceFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the source.
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
     * Get the ingested documents in this source.
     *
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * Get the indexed chunks in this source.
     *
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    /**
     * Get the tool contracts that retrieve from this source.
     *
     * @return HasMany<ToolContract, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(ToolContract::class);
    }

    /**
     * Whether the source may be retrieved from by the runtime.
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
            'status' => KnowledgeSourceStatus::class,
            'sensitivity' => Sensitivity::class,
            'requires_approval' => 'boolean',
            'environments' => 'array',
            'document_count' => 'integer',
            'chunk_count' => 'integer',
            'last_indexed_at' => 'datetime',
        ];
    }
}
