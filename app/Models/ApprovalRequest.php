<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\Sensitivity;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A governance approval gating a sensitive change before it takes effect.
 *
 * @property string $id
 * @property int $team_id
 * @property string|null $application_id
 * @property string|null $project_id
 * @property ApprovalType $type
 * @property ApprovalStatus $status
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $title
 * @property string|null $summary
 * @property Sensitivity|null $sensitivity
 * @property Environment|null $environment
 * @property int|null $requested_by
 * @property string|null $requested_label
 * @property int|null $decided_by
 * @property string|null $decided_label
 * @property string|null $decision_note
 * @property Carbon|null $decided_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Application|null $application
 * @property-read Project|null $project
 * @property-read User|null $requester
 * @property-read User|null $decider
 * @property-read Model|null $subject
 */
#[Fillable(['team_id', 'application_id', 'project_id', 'type', 'status', 'subject_type', 'subject_id', 'title', 'summary', 'sensitivity', 'environment', 'requested_by', 'requested_label', 'decided_by', 'decided_label', 'decision_note', 'decided_at', 'metadata'])]
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /**
     * Get the team the approval is recorded under.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the application the change relates to, if any.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the project the change relates to, if any.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that requested the change.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user that decided the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Get the record the approval gates (tool contract, agent, model, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope the query to pending requests.
     *
     * @param  Builder<ApprovalRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending);
    }

    /**
     * Determine whether the request is still awaiting a decision.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Resolve the team this approval is audited under.
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
            'type' => ApprovalType::class,
            'status' => ApprovalStatus::class,
            'sensitivity' => Sensitivity::class,
            'environment' => Environment::class,
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
