<?php

namespace App\Support\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\AuditEvent;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * Builds the read model for the MAACC Access Control console page (Phase 8B): the
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
    public function forConsole(?Request $request = null): array
    {
        $request ??= request();
        [$admins, $adminPagination] = $this->admins($request);
        [$audit, $auditPagination] = $this->auditTrail($request);

        return [
            'roles' => $this->roleCatalogue(),
            'permissionGroups' => $this->permissionGroups(),
            'admins' => $admins,
            'review' => $this->review(),
            'audit' => $audit,
            'pagination' => [
                'admins' => $adminPagination,
                'audit' => $auditPagination,
            ],
        ];
    }

    /**
     * The selectable user directory for assigning a platform role.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public function directory(?Request $request = null): array
    {
        return $this->directoryPage($request)['items'];
    }

    /**
     * A bounded, searchable directory page for role assignment.
     *
     * @return array{items: array<int, array{id: int, name: string, email: string}>, pagination: array<string, mixed>}
     */
    public function directoryPage(?Request $request = null): array
    {
        $request ??= request();
        $search = mb_substr($request->string('directory_q')->trim()->value(), 0, 100);
        $query = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%"));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($this->pageSize($request), ['id', 'name', 'email'], 'directory_cursor');

        return [
            'items' => collect($query->items())
                ->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
            'pagination' => $this->pagination($query, $request, ['directory_q']),
        ];
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
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function admins(Request $request): array
    {
        $paginator = User::query()
            ->has('roles')
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($this->pageSize($request), cursorName: 'admins_cursor');
        $admins = collect($paginator->items());
        $grants = PlatformAccessGrant::query()
            ->active()
            ->whereIn('user_id', $admins->pluck('id'))
            ->with(['grantedBy', 'user'])
            ->get()
            ->groupBy('user_id');

        return [$admins
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
            ->all(), $this->pagination($paginator, $request)];
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
            'dueForExpiry' => $this->access->dueForExpiry(100)->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
            'needingCertification' => $this->access->needingCertification(100)->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
            'stale' => $this->access->staleGrants(100)->map(fn (PlatformAccessGrant $g): array => $this->grantRow($g))->all(),
        ];
    }

    /**
     * The most recent platform-access audit events.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function auditTrail(Request $request): array
    {
        $paginator = AuditEvent::query()
            ->where('action', 'like', 'platform_access.%')
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate($this->pageSize($request), cursorName: 'access_audit_cursor');

        return [collect($paginator->items())
            ->map(static fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'actor' => $event->actor_label,
                'metadata' => $event->metadata,
                'at' => $event->created_at?->toIso8601String(),
            ])
            ->all(), $this->pagination($paginator, $request)];
    }

    private function pageSize(Request $request): int
    {
        return min(100, max(10, $request->integer('per_page', 25)));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  CursorPaginator<TKey, TValue>  $paginator
     * @param  array<int, string>  $filters
     * @return array<string, mixed>
     */
    private function pagination(CursorPaginator $paginator, Request $request, array $filters = []): array
    {
        return [
            'count' => $paginator->count(),
            'perPage' => $paginator->perPage(),
            'hasMore' => $paginator->hasMorePages(),
            'nextCursor' => $paginator->nextCursor()?->encode(),
            'previousCursor' => $paginator->previousCursor()?->encode(),
            'filters' => $request->only($filters),
        ];
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
