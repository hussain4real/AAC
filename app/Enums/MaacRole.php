<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * MAAC platform roles (Phase 2 "concepts + policies" RBAC). A user holds a
 * MaacRole within a project via the `project_members` pivot; platform-level
 * authority is derived from the team {@see TeamRole} (Owner/Admin) and treated
 * as {@see MaacRole::PlatformAdmin}.
 */
enum MaacRole: string
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
     * @return array<int, MaacPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::PlatformAdmin => MaacPermission::cases(),
            self::ProjectOwner => [
                MaacPermission::ManageProject,
                MaacPermission::ManageAgent,
                MaacPermission::ManageTool,
                MaacPermission::PublishAgent,
                MaacPermission::ApproveTool,
                MaacPermission::View,
            ],
            self::Developer => [
                MaacPermission::ManageAgent,
                MaacPermission::ManageTool,
                MaacPermission::View,
            ],
            self::Viewer => [
                MaacPermission::View,
            ],
            self::Auditor => [
                MaacPermission::View,
                MaacPermission::ViewAudit,
            ],
            self::SecurityReviewer => [
                MaacPermission::View,
                MaacPermission::ViewAudit,
                MaacPermission::ReviewSecurity,
                MaacPermission::ApproveTool,
            ],
        };
    }

    /**
     * Determine if the role grants the given permission.
     */
    public function hasPermission(MaacPermission $permission): bool
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
