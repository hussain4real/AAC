<?php

namespace App\Models;

use App\Enums\TraceEventType;
use Database\Factories\TraceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_run_id
 * @property TraceEventType $type
 * @property string|null $message
 * @property array<string, mixed>|null $data
 * @property int $sequence
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentRun $agentRun
 */
#[Fillable(['agent_run_id', 'type', 'message', 'data', 'sequence', 'occurred_at'])]
class TraceEvent extends Model
{
    /** @use HasFactory<TraceEventFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the run the trace event belongs to.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TraceEventType::class,
            'data' => 'array',
            'sequence' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
