<?php

namespace App\Support;

use App\Enums\MaacPermission;
use App\Enums\MaacRole;
use App\Enums\TeamRole;
use App\Http\Resources\Maac\ApprovalRequestResource;
use App\Http\Resources\Maac\AuditEventResource;
use App\Models\GovernanceSetting;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QuotaLimit;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Assembles the governance console dataset for a team — approval queues, audit
 * log, role matrix, security policies, retention/masking settings, and quotas —
 * as plain arrays matching the console contract (resources/js/maac/data.ts).
 * Replaces the Phase 1/2 fixture rollups with real records.
 */
class GovernanceConsoleData
{
    /**
     * Short descriptions for each MAAC role shown on the governance role cards.
     *
     * @var array<string, string>
     */
    private const ROLE_DESCRIPTIONS = [
        'platform_admin' => 'Full platform control: global settings, models, credentials, policies, and audit access.',
        'project_owner' => 'Owns projects: approves project agents and tools and manages members.',
        'developer' => 'Creates agents, defines tool contracts, and implements SDK handlers.',
        'viewer' => 'Read-only access to agents, dashboards, and permitted reports.',
        'auditor' => 'Reviews audit logs and run traces across the platform.',
        'security_reviewer' => 'Reviews data boundaries and traces, and approves sensitive tool contracts.',
    ];

    /**
     * Build the governance dataset for the given team.
     *
     * @return array{
     *     approvals: array{tools: array<int, mixed>, agents: array<int, mixed>, models: array<int, mixed>, data: array<int, mixed>},
     *     auditEvents: array<int, array<string, mixed>>,
     *     roles: array<int, array<string, mixed>>,
     *     policies: array<int, array{name: string, on: bool, desc: string}>,
     *     governanceSettings: array<string, mixed>,
     *     quotas: array<int, array<string, mixed>>,
     * }
     */
    public static function forTeam(Team $team): array
    {
        $settings = GovernanceSetting::forTeam($team);

        return [
            'approvals' => self::approvals($team),
            'auditEvents' => self::auditEvents($team),
            'roles' => self::roles($team),
            'policies' => self::policies($settings),
            'governanceSettings' => self::settings($settings),
            'quotas' => self::quotas($team),
        ];
    }

    /**
     * Group pending approval requests into the four console queues.
     *
     * @return array{tools: array<int, mixed>, agents: array<int, mixed>, models: array<int, mixed>, data: array<int, mixed>}
     */
    private static function approvals(Team $team): array
    {
        $items = collect(ApprovalRequestResource::collection(
            $team->approvalRequests()->pending()->with(['application', 'subject'])->latest()->get()
        )->resolve());

        return [
            'tools' => $items->where('queue', 'tools')->values()->all(),
            'agents' => $items->where('queue', 'agents')->values()->all(),
            'models' => $items->where('queue', 'models')->values()->all(),
            'data' => $items->where('queue', 'data')->values()->all(),
        ];
    }

    /**
     * Build the recent audit log entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function auditEvents(Team $team): array
    {
        return AuditEventResource::collection(
            $team->auditEvents()->with('actor')->latest()->limit(50)->get()
        )->resolve();
    }

    /**
     * Build the role matrix with real user counts per MAAC role.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function roles(Team $team): array
    {
        $projectIds = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->pluck('id');

        $counts = ProjectMember::query()
            ->whereIn('project_id', $projectIds)
            ->get()
            ->groupBy(fn (ProjectMember $member): string => $member->maac_role->value)
            ->map(fn ($group) => $group->pluck('user_id')->unique()->count());

        $platformAdmins = $team->members()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->count();

        return array_map(fn (MaacRole $role): array => [
            'name' => $role->label(),
            'users' => $role === MaacRole::PlatformAdmin ? $platformAdmins : ($counts[$role->value] ?? 0),
            'desc' => self::ROLE_DESCRIPTIONS[$role->value],
            'perms' => array_map(
                fn (MaacPermission $permission): string => Str::headline($permission->name),
                $role->permissions(),
            ),
        ], MaacRole::cases());
    }

    /**
     * Build the security policy toggles, reflecting the team's real settings.
     *
     * @return array<int, array{name: string, on: bool, desc: string}>
     */
    private static function policies(GovernanceSetting $settings): array
    {
        return [
            ['name' => 'Client-side data isolation', 'on' => true, 'desc' => 'MAAC never holds credentials for or directly queries application production databases.'],
            ['name' => 'Tool result masking', 'on' => $settings->mask_sensitive_inputs || $settings->mask_sensitive_outputs, 'desc' => 'Confidential tool arguments and results are masked before being written to logs.'],
            ['name' => 'Restricted logging blocked', 'on' => $settings->block_restricted_logging, 'desc' => 'Restricted tool payloads are blocked from raw logging entirely.'],
            ['name' => 'Approval before production', 'on' => true, 'desc' => 'Sensitive tools, agent publication, and model promotion require owner approval.'],
            ['name' => 'Daily run quota', 'on' => $settings->default_daily_run_quota !== null, 'desc' => 'A default per-environment cap limits the number of agent runs per day.'],
        ];
    }

    /**
     * Snapshot the team's retention/masking governance settings.
     *
     * @return array<string, mixed>
     */
    private static function settings(GovernanceSetting $settings): array
    {
        return [
            'retainPromptsDays' => $settings->retain_prompts_days,
            'retainResponsesDays' => $settings->retain_responses_days,
            'retainToolArgumentsDays' => $settings->retain_tool_arguments_days,
            'retainToolResultsDays' => $settings->retain_tool_results_days,
            'auditRetentionDays' => $settings->audit_retention_days,
            'maskSensitiveInputs' => $settings->mask_sensitive_inputs,
            'maskSensitiveOutputs' => $settings->mask_sensitive_outputs,
            'blockRestrictedLogging' => $settings->block_restricted_logging,
            'defaultDailyRunQuota' => $settings->default_daily_run_quota,
        ];
    }

    /**
     * Build the configured rate limits / quotas.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function quotas(Team $team): array
    {
        return $team->quotaLimits()
            ->orderBy('scope')
            ->get()
            ->map(fn (QuotaLimit $quota): array => [
                'id' => $quota->id,
                'scope' => $quota->scope->label(),
                'scopeKey' => $quota->scope->value,
                'subjectId' => $quota->subject_id,
                'environment' => $quota->environment?->label() ?? 'All',
                'maxRunsPerDay' => $quota->max_runs_per_day,
                'maxTokensPerDay' => $quota->max_tokens_per_day,
                'enabled' => $quota->enabled,
            ])
            ->all();
    }
}
