<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * MAAC platform-administration roles (Phase 8B).
 *
 * These are MAAC's own internal operator roles, held globally (not per-tenant)
 * and backed by Spatie. They sit ABOVE the team/project-scoped {@see MaacRole}
 * that governs a tenant user's application workflows: a user with no platform
 * role is a pure tenant user, while a platform role grants cross-tenant MAAC
 * administration. {@see PlatformRole::SuperAdmin} additionally bypasses every
 * authorization gate (a `Gate::before` override).
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super-admin';
    case PlatformAdmin = 'platform-admin';
    case SecurityReviewer = 'security-reviewer';
    case Auditor = 'auditor';
    case SupportOperator = 'support-operator';
    case ReleaseManager = 'release-manager';
    case ReadOnlyObserver = 'read-only-observer';

    /**
     * The human-readable label, e.g. "Super Admin".
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * A short description of the role's remit (for the admin UI).
     */
    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Unrestricted MAAC platform control, including break-glass and assigning any role.',
            self::PlatformAdmin => 'Full operational control of the platform, tenants, and governance (no emergency break-glass).',
            self::SecurityReviewer => 'Review and decide approvals, approve tools, read audits, and run incident containment.',
            self::Auditor => 'Read-only access across the platform plus signed audit export.',
            self::SupportOperator => 'Investigate runs and replay webhooks to support tenant applications.',
            self::ReleaseManager => 'Promote agents/models and manage SDK distribution and release approvals.',
            self::ReadOnlyObserver => 'Read-only visibility across the platform with no change or export rights.',
        };
    }

    /**
     * The platform permissions this role grants.
     *
     * @return array<int, PlatformPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PlatformPermission::cases(),
            self::PlatformAdmin => array_values(array_filter(
                PlatformPermission::cases(),
                static fn (PlatformPermission $permission): bool => $permission !== PlatformPermission::ActivateBreakGlass,
            )),
            self::SecurityReviewer => [
                PlatformPermission::AccessAdmin,
                PlatformPermission::ViewUsers,
                PlatformPermission::ReviewAccess,
                PlatformPermission::ViewTeams,
                PlatformPermission::ViewApplications,
                PlatformPermission::ViewProjects,
                PlatformPermission::ViewAgents,
                PlatformPermission::ViewTools,
                PlatformPermission::ApproveTools,
                PlatformPermission::ViewModels,
                PlatformPermission::ViewCredentials,
                PlatformPermission::ViewRuns,
                PlatformPermission::ViewWebhooks,
                PlatformPermission::ViewApprovals,
                PlatformPermission::DecideApprovals,
                PlatformPermission::ViewAudits,
                PlatformPermission::ExportAudits,
                PlatformPermission::ManageIncidents,
            ],
            self::Auditor => [
                PlatformPermission::AccessAdmin,
                PlatformPermission::ViewTeams,
                PlatformPermission::ViewUsers,
                PlatformPermission::ViewApplications,
                PlatformPermission::ViewProjects,
                PlatformPermission::ViewAgents,
                PlatformPermission::ViewTools,
                PlatformPermission::ViewModels,
                PlatformPermission::ViewCredentials,
                PlatformPermission::ViewQuotas,
                PlatformPermission::ViewRuns,
                PlatformPermission::ViewWebhooks,
                PlatformPermission::ViewApprovals,
                PlatformPermission::ViewAudits,
                PlatformPermission::ExportAudits,
            ],
            self::SupportOperator => [
                PlatformPermission::AccessAdmin,
                PlatformPermission::ViewApplications,
                PlatformPermission::ViewProjects,
                PlatformPermission::ViewAgents,
                PlatformPermission::ViewTools,
                PlatformPermission::ViewCredentials,
                PlatformPermission::ViewQuotas,
                PlatformPermission::ViewRuns,
                PlatformPermission::ViewWebhooks,
                PlatformPermission::ManageWebhooks,
            ],
            self::ReleaseManager => [
                PlatformPermission::AccessAdmin,
                PlatformPermission::ViewApplications,
                PlatformPermission::ViewProjects,
                PlatformPermission::ViewAgents,
                PlatformPermission::PublishAgents,
                PlatformPermission::ViewTools,
                PlatformPermission::ApproveTools,
                PlatformPermission::ViewModels,
                PlatformPermission::ManageModels,
                PlatformPermission::ViewApprovals,
                PlatformPermission::DecideApprovals,
                PlatformPermission::ViewRuns,
                PlatformPermission::ManageSdk,
                PlatformPermission::ViewAudits,
            ],
            self::ReadOnlyObserver => [
                PlatformPermission::AccessAdmin,
                ...array_values(array_filter(
                    PlatformPermission::cases(),
                    static fn (PlatformPermission $permission): bool => $permission->action() === 'view',
                )),
            ],
        };
    }

    /**
     * The permission values (Spatie names) this role grants.
     *
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(static fn (PlatformPermission $permission): string => $permission->value, $this->permissions());
    }

    /**
     * All role values (the Spatie role names).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * All roles as value/label/description option rows.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'description' => $case->description()],
            self::cases(),
        );
    }
}
