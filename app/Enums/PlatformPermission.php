<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Granular MAAC platform-administration permissions (Phase 8B).
 *
 * These are the Spatie permission names that gate MAAC's own internal
 * administration — the cross-tenant platform controls held by MAAC admins, as
 * distinct from the team/project-scoped {@see MaacPermission} that governs a
 * tenant user's own application workflows. A {@see PlatformRole} grants a subset
 * of these, and the seeder materializes them into the Spatie permission table.
 */
enum PlatformPermission: string
{
    // Console access + platform user / role administration.
    case AccessAdmin = 'platform.access';
    case ViewUsers = 'users.view';
    case ManageUsers = 'users.manage';
    case AssignRoles = 'roles.assign';
    case ReviewAccess = 'access.review';
    case ActivateBreakGlass = 'breakglass.activate';

    // Cross-tenant visibility + management.
    case ViewTeams = 'teams.view';
    case ViewApplications = 'applications.view';
    case ManageApplications = 'applications.manage';
    case ViewProjects = 'projects.view';
    case ManageProjects = 'projects.manage';
    case ViewAgents = 'agents.view';
    case ManageAgents = 'agents.manage';
    case PublishAgents = 'agents.publish';
    case ViewTools = 'tools.view';
    case ManageTools = 'tools.manage';
    case ApproveTools = 'tools.approve';
    case ViewModels = 'models.view';
    case ManageModels = 'models.manage';
    case ViewCredentials = 'credentials.view';
    case ManageCredentials = 'credentials.manage';
    case ViewQuotas = 'quotas.view';
    case ManageQuotas = 'quotas.manage';
    case ViewRuns = 'runs.view';
    case ViewWebhooks = 'webhooks.view';
    case ManageWebhooks = 'webhooks.manage';

    // Governance + operations.
    case ViewApprovals = 'approvals.view';
    case DecideApprovals = 'approvals.decide';
    case ViewAudits = 'audits.view';
    case ExportAudits = 'audits.export';
    case ManageSdk = 'sdk.manage';
    case ManageIncidents = 'incidents.manage';
    case ManageSettings = 'settings.manage';

    /**
     * The human-readable label, e.g. "Applications · Manage".
     */
    public function label(): string
    {
        return Str::headline($this->group()).' · '.Str::headline($this->action());
    }

    /**
     * The resource domain this permission belongs to (for grouped display).
     */
    public function group(): string
    {
        return Str::before($this->value, '.');
    }

    /**
     * The action portion of the permission, e.g. "manage".
     */
    public function action(): string
    {
        return Str::after($this->value, '.');
    }

    /**
     * All permission values (the Spatie permission names).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * All permissions as value/label/group option rows.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'group' => $case->group()],
            self::cases(),
        );
    }
}
