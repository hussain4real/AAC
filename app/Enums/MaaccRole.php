<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * MAACC platform roles (Phase 2 "concepts + policies" RBAC). A user holds a
 * MaaccRole within a project via the `project_members` pivot; platform-level
 * authority is derived from the team {@see TeamRole} (Owner/Admin) and treated
 * as {@see MaaccRole::PlatformAdmin}.
 */
enum MaaccRole: string
{
    case PlatformAdmin = 'platform_admin';
    case ProjectOwner = 'project_owner';
    case Developer = 'developer';
    case Viewer = 'viewer';
    case Auditor = 'auditor';
    case SecurityReviewer = 'security_reviewer';

    /**
     * Get the display label for the role (e.g. "Platform Admin").
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Get the permissions granted by this role.
     *
     * @return array<int, MaaccPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::PlatformAdmin => MaaccPermission::cases(),
            self::ProjectOwner => [
                MaaccPermission::ManageProject,
                MaaccPermission::ManageAgent,
                MaaccPermission::ManageTool,
                MaaccPermission::PublishAgent,
                MaaccPermission::ApproveTool,
                MaaccPermission::View,
            ],
            self::Developer => [
                MaaccPermission::ManageAgent,
                MaaccPermission::ManageTool,
                MaaccPermission::View,
            ],
            self::Viewer => [
                MaaccPermission::View,
            ],
            self::Auditor => [
                MaaccPermission::View,
                MaaccPermission::ViewAudit,
            ],
            self::SecurityReviewer => [
                MaaccPermission::View,
                MaaccPermission::ViewAudit,
                MaaccPermission::ReviewSecurity,
                MaaccPermission::ApproveTool,
            ],
        };
    }

    /**
     * Determine if the role grants the given permission.
     */
    public function hasPermission(MaaccPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Get all roles as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
