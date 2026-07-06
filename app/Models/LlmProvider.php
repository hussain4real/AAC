<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\LlmStatus;
use App\Enums\LlmVerificationOutcome;
use App\Enums\Sensitivity;
use App\Support\Secrets\Contracts\SecretVault;
use Carbon\CarbonInterface;
use Database\Factories\LlmProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $slug
 * @property string $name
 * @property string $code
 * @property string $provider
 * @property string $context_window
 * @property float $input_cost
 * @property float $output_cost
 * @property Sensitivity $sensitivity
 * @property array<int, string> $environments
 * @property LlmStatus $status
 * @property string|null $vault_secret_id
 * @property Carbon|null $verified_at
 * @property LlmVerificationOutcome|null $verification_status
 * @property string|null $verification_message
 * @property Carbon|null $verification_checked_at
 * @property int $usage_pct
 * @property int $runs_count
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read VaultSecret|null $vaultSecret
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Agent> $agents
 */
#[Fillable(['team_id', 'slug', 'name', 'code', 'provider', 'context_window', 'input_cost', 'output_cost', 'sensitivity', 'environments', 'status', 'vault_secret_id', 'usage_pct', 'runs_count', 'note'])]
class LlmProvider extends Model
{
    /** @use HasFactory<LlmProviderFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /**
     * Get the team that owns the model catalog entry.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the projects this model is approved for.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_llm_provider')->withTimestamps();
    }

    /**
     * Get the agents configured to use this model.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the vault secret that holds this model's API key, if one is bound.
     *
     * @return BelongsTo<VaultSecret, $this>
     */
    public function vaultSecret(): BelongsTo
    {
        return $this->belongsTo(VaultSecret::class, 'vault_secret_id');
    }

    /**
     * Resolve the provider's API key from the secrets vault when one is bound,
     * recording the access. Returns null when no vault key is configured, in
     * which case the runtime falls back to the environment/config-driven key.
     */
    public function resolveApiKey(SecretVault $vault): ?string
    {
        $secret = $this->vaultSecret;

        return $secret instanceof VaultSecret ? $vault->read($secret) : null;
    }

    /**
     * Maps a catalog provider label to a configured `laravel/ai` driver.
     *
     * @var array<string, string>
     */
    private const DRIVER_MAP = [
        'azure' => 'azure',
        'bedrock' => 'bedrock',
        'vertex' => 'gemini',
        'google' => 'gemini',
        'gemini' => 'gemini',
        'anthropic' => 'anthropic',
        'claude' => 'anthropic',
        'openrouter' => 'openrouter',
        'openai' => 'openai',
        'groq' => 'groq',
        'deepseek' => 'deepseek',
        'mistral' => 'mistral',
        'ollama' => 'ollama',
        'grok' => 'xai',
        'xai' => 'xai',
    ];

    /**
     * Resolve the `laravel/ai` provider driver that backs this catalog entry,
     * falling back to the application's default AI provider.
     */
    public function driver(): string
    {
        return self::driverFor($this->provider);
    }

    /**
     * Map a provider label to its configured `laravel/ai` driver, falling back
     * to the application's default AI provider when none matches.
     */
    public static function driverFor(string $provider): string
    {
        $normalized = strtolower($provider);

        foreach (self::DRIVER_MAP as $needle => $driver) {
            if (str_contains($normalized, $needle)) {
                return $driver;
            }
        }

        return (string) config('ai.default');
    }

    /**
     * Determine whether the model may be used to run agents in the given
     * environment (approved status and environment availability).
     */
    public function isAvailableIn(string $environment): bool
    {
        return $this->status === LlmStatus::Approved
            && in_array($environment, $this->environments, true);
    }

    /**
     * Whether the most recent live connection check passed. Publishing a model
     * to the catalog requires a passing check, so a wrong model code or key can
     * never reach production traffic.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === LlmVerificationOutcome::Ok;
    }

    /**
     * Record the result of a live connection check. `verified_at` only advances
     * on success, so it always marks the last time the model actually connected.
     */
    public function recordVerification(LlmVerificationOutcome $outcome, string $message, CarbonInterface $at): void
    {
        $this->forceFill([
            'verification_status' => $outcome,
            'verification_message' => $message,
            'verification_checked_at' => $at,
            'verified_at' => $outcome->isSuccess() ? $at : $this->verified_at,
        ])->save();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this model is audited under.
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
            'sensitivity' => Sensitivity::class,
            'status' => LlmStatus::class,
            'environments' => 'array',
            'input_cost' => 'float',
            'output_cost' => 'float',
            'usage_pct' => 'integer',
            'runs_count' => 'integer',
            'verified_at' => 'datetime',
            'verification_status' => LlmVerificationOutcome::class,
            'verification_checked_at' => 'datetime',
        ];
    }
}
