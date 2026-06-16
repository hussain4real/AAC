<?php

namespace App\Models;

use App\Enums\AgentStatus;
use Database\Factories\AgentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_id
 * @property string $version
 * @property string $system_prompt
 * @property string $llm_provider_id
 * @property float $temperature
 * @property int $max_tokens
 * @property array<string, mixed>|null $settings
 * @property AgentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 * @property-read LlmProvider $llmProvider
 * @property-read User|null $publisher
 */
#[Fillable(['agent_id', 'version', 'system_prompt', 'llm_provider_id', 'temperature', 'max_tokens', 'settings', 'status', 'published_at', 'published_by', 'notes'])]
class AgentVersion extends Model
{
    /** @use HasFactory<AgentVersionFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the agent the version belongs to.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the LLM model captured in this version snapshot.
     *
     * @return BelongsTo<LlmProvider, $this>
     */
    public function llmProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class);
    }

    /**
     * Get the user that published the version.
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
