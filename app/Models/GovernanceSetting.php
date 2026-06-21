<?php

namespace App\Models;

use App\Enums\Environment;
use Database\Factories\GovernanceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-team governance configuration: payload retention windows, masking
 * toggles, audit retention, and a default per-environment run quota. Resolved
 * for a team via {@see self::forTeam()} (returns a default, unsaved instance
 * until an admin persists changes).
 *
 * @property int $id
 * @property int $team_id
 * @property int $retain_prompts_days
 * @property int $retain_responses_days
 * @property int $retain_tool_arguments_days
 * @property int $retain_tool_results_days
 * @property int $audit_retention_days
 * @property bool $mask_sensitive_inputs
 * @property bool $mask_sensitive_outputs
 * @property bool $block_restricted_logging
 * @property int|null $default_daily_run_quota
 * @property array<string, array<string, mixed>>|null $environment_overrides
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'retain_prompts_days', 'retain_responses_days', 'retain_tool_arguments_days', 'retain_tool_results_days', 'audit_retention_days', 'mask_sensitive_inputs', 'mask_sensitive_outputs', 'block_restricted_logging', 'default_daily_run_quota', 'environment_overrides'])]
class GovernanceSetting extends Model
{
    /** @use HasFactory<GovernanceSettingFactory> */
    use HasFactory;

    /**
     * The model's default attribute values (mirroring the migration defaults).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'retain_prompts_days' => 90,
        'retain_responses_days' => 90,
        'retain_tool_arguments_days' => 30,
        'retain_tool_results_days' => 30,
        'audit_retention_days' => 365,
        'mask_sensitive_inputs' => true,
        'mask_sensitive_outputs' => true,
        'block_restricted_logging' => true,
    ];

    /**
     * Resolve the governance settings for a team, falling back to a default
     * (unsaved) instance so reads never write during request handling.
     */
    public static function forTeam(Team $team): self
    {
        return static::firstOrNew(['team_id' => $team->id]);
    }

    /**
     * Get the team the settings belong to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Resolve the retention window (in days) for a payload category, honoring
     * any per-environment override.
     */
    public function retentionDaysFor(string $field, ?Environment $environment = null): int
    {
        $column = match ($field) {
            'prompts' => 'retain_prompts_days',
            'responses' => 'retain_responses_days',
            'tool_arguments' => 'retain_tool_arguments_days',
            'tool_results' => 'retain_tool_results_days',
            default => 'audit_retention_days',
        };

        return (int) $this->resolve($column, $environment);
    }

    /**
     * Determine whether sensitive run inputs should be masked in the given
     * environment.
     */
    public function masksInputs(?Environment $environment = null): bool
    {
        return (bool) $this->resolve('mask_sensitive_inputs', $environment);
    }

    /**
     * Determine whether sensitive run outputs should be masked in the given
     * environment.
     */
    public function masksOutputs(?Environment $environment = null): bool
    {
        return (bool) $this->resolve('mask_sensitive_outputs', $environment);
    }

    /**
     * Determine whether raw logging of restricted payloads is blocked.
     */
    public function blocksRestrictedLogging(?Environment $environment = null): bool
    {
        return (bool) $this->resolve('block_restricted_logging', $environment);
    }

    /**
     * Resolve the default daily run quota for the given environment, if any.
     */
    public function dailyRunQuota(?Environment $environment = null): ?int
    {
        $value = $this->resolve('default_daily_run_quota', $environment);

        return $value === null ? null : (int) $value;
    }

    /**
     * Resolve a setting value, applying a per-environment override when present.
     */
    private function resolve(string $column, ?Environment $environment): mixed
    {
        if ($environment !== null) {
            $overrides = $this->environment_overrides[$environment->value] ?? [];

            if (array_key_exists($column, $overrides)) {
                return $overrides[$column];
            }
        }

        return $this->getAttribute($column);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retain_prompts_days' => 'integer',
            'retain_responses_days' => 'integer',
            'retain_tool_arguments_days' => 'integer',
            'retain_tool_results_days' => 'integer',
            'audit_retention_days' => 'integer',
            'mask_sensitive_inputs' => 'boolean',
            'mask_sensitive_outputs' => 'boolean',
            'block_restricted_logging' => 'boolean',
            'default_daily_run_quota' => 'integer',
            'environment_overrides' => 'array',
        ];
    }
}
