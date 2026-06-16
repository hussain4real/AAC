<?php

namespace App\Models;

use App\Enums\ExecMode;
use App\Enums\ToolCallStatus;
use Database\Factories\ToolCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_run_id
 * @property string|null $tool_contract_id
 * @property string $tool_name
 * @property ToolCallStatus $status
 * @property array<string, mixed>|null $arguments
 * @property array<string, mixed>|null $result
 * @property ExecMode|null $execution_mode
 * @property int $sequence
 * @property Carbon|null $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentRun $agentRun
 * @property-read ToolContract|null $toolContract
 */
#[Fillable(['agent_run_id', 'tool_contract_id', 'tool_name', 'status', 'arguments', 'result', 'execution_mode', 'sequence', 'requested_at', 'completed_at'])]
class ToolCall extends Model
{
    /** @use HasFactory<ToolCallFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the run the tool call belongs to.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Get the tool contract that was invoked.
     *
     * @return BelongsTo<ToolContract, $this>
     */
    public function toolContract(): BelongsTo
    {
        return $this->belongsTo(ToolContract::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ToolCallStatus::class,
            'arguments' => 'array',
            'result' => 'array',
            'execution_mode' => ExecMode::class,
            'sequence' => 'integer',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
