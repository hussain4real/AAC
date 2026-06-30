<?php

namespace App\Support\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\AuditEvent;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the read model for the MAAC Access Control console page (Phase 8B): the
 * platform role/permission catalogue, every platform administrator with their
 * active grants, the access-review work lists, and the recent platform-access
 * audit trail. Read-only; all mutation goes through {@see PlatformAccessManager}.
 */
class PlatformAccessReport
{
    public function __construct(private readonly PlatformAccessManager $access = new PlatformAccessManager) {}

    /**
     * The full Access Control dataset for the console.
     *
     * @return array<string, mixed>
     */
    public function forConsole(): array
    {
        return [
            'roles' => $this->roleCatalogue(),
            'permissionGroups' => $this->permissionGroups(),
            'admins' => $this->admins(),
            'review' => $this->review(),
            'audit' => $this->auditTrail(),
        ];
    }

    /**
     * The selectable user directory for assigning a platform role.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public function directory(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * The viewing user's relevant platform capabilities, for gating the UI.
     *
     * @return array<string, bool>
     */
    public function capabilities(User $user): array
    {
        return [
            'isSuperAdmin' => $user->isPlatformSuperAdmin(),
            'canAssignRoles' => $user->hasPlatformPermission(PlatformPermission::AssignRoles),
            'canBreakGlass' => $user->hasPlatformPermission(PlatformPermission::ActivateBreakGlass),
            'canReviewAccess' => $user->hasPlatformPermission(PlatformPermission::ReviewAccess),
        ];
    }

    /**
     * The platform role catalogue with each role's granted permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function roleCatalogue(): array
    {
        return array_map(static fn (PlatformRole $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'permissions' => $role->permissionValues(),
            'permissionCount' => count($role->permissionValues()),
        ], PlatformRole::cases());
    }

    /**
     * The permission catalogue grouped by resource domain.
     *
     * @return array<int, array{group: string, permissions: array<int, array{value: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        return collect(PlatformPermission::cases())
            ->groupBy(static fn (PlatformPermission $permission): string => $permission->group())
            ->map(static fn (Collection $items, string $group): array => [
                'group' => $group,
                'permissions' => $items->map(static fn (PlatformPermission $permission): array => [
                    'value' => $permission->value,
                    'label' => $permission->label(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Every platform administrator (a user holding at least one platform role)
     * with their roles and active grants.
     *
     * @return array<int, array<string, mixed>>
     */
    private function admins(): array
    {
        $grants = PlatformAccessGrant::query()->active()->with('grantedBy')->get()->groupBy('user_id');

        return User::query()
            ->has('roles')
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->platformRoleValues(),
                'isSuperAdmin' => $user->isPlatformSuperAdmin(),
                'grants' => ($grants->get($user->id) ?? collect())
                    ->map(fn (PlatformAccessGrant $grant): array => $this->grantRow($grant))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The access-review work lists: expiring break-glass, grants needing
     * certification, and stale admin grants.
     *
     * @return array<string, mixed>
     */
    private function review(): array
    {
        return [
            'dueForExpiry' => $this->access->dueForExpiry()->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
            'needingCertification' => $this->access->needingCertification()->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
            'stale' => $this->access->staleGrants()->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
        ];
    }

    /**
     * The most recent platform-access audit events.
     *
     * @return array<int, array<string, mixed>>
     */
    private function auditTrail(): array
    {
        return AuditEvent::query()
            ->where('action', 'like', 'platform_access.%')
            ->latest()
            ->limit(50)
            ->get()
            ->map(static fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'actor' => $event->actor_label,
                'metadata' => $event->metadata,
                'at' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Serialize a grant for the console.
     *
     * @return array<string, mixed>
     */
    private function grantRow(PlatformAccessGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'userId' => $grant->user_id,
            'userName' => $grant->user->name,
            'userEmail' => $grant->user->email,
            'role' => $grant->role->value,
            'roleLabel' => $grant->role->label(),
            'kind' => $grant->kind->value,
            'reason' => $grant->reason,
            'grantedBy' => $grant->granted_by !== null ? $grant->grantedBy->name : 'system',
            'expiresAt' => $grant->expires_at?->toIso8601String(),
            'certifiedAt' => $grant->certified_at?->toIso8601String(),
            'createdAt' => $grant->created_at?->toIso8601String(),
            'isBreakGlass' => $grant->isBreakGlass(),
        ];
    }
}
