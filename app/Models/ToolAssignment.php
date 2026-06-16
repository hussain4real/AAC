<?php

namespace App\Models;

use App\Enums\Environment;
use App\Enums\ToolScope;
use Database\Factories\ToolAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tool_contract_id
 * @property ToolScope $scope
 * @property string|null $project_id
 * @property string|null $agent_id
 * @property Environment|null $environment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ToolContract $toolContract
 * @property-read Project|null $project
 * @property-read Agent|null $agent
 */
#[Fillable(['tool_contract_id', 'scope', 'project_id', 'agent_id', 'environment'])]
class ToolAssignment extends Model
{
    /** @use HasFactory<ToolAssignmentFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the tool contract being assigned.
     *
     * @return BelongsTo<ToolContract, $this>
     */
    public function toolContract(): BelongsTo
    {
        return $this->belongsTo(ToolContract::class);
    }

    /**
     * Get the project the tool is assigned to (project-scoped assignments).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the agent the tool is assigned to (agent-scoped assignments).
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ToolScope::class,
            'environment' => Environment::class,
        ];
    }
}
