<?php

namespace App\Support;

use App\Enums\MaaccPermission;
use App\Enums\MaaccRole;
use App\Models\ProjectMember;
use App\Models\Team;
use App\Models\User;

class MaaccAccess
{
    /**
     * Build the server-authoritative console access snapshot for an actor.
     *
     * @return array{
     *     roles: array<int, string>,
     *     permissions: array<int, string>,
     *     navigation: array<int, string>,
     *     projectIds: array<int, string>,
     *     isPlatformAdmin: bool,
     *     roleLabel: string
     * }
     */
    public function forUser(User $user, Team $team): array
    {
        $isPlatformAdmin = $user->isMaaccPlatformAdmin($team);
        $memberships = $user->projectMemberships()
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->get();
        $roles = $isPlatformAdmin
            ? collect([MaaccRole::PlatformAdmin])
            : $memberships->map(fn (ProjectMember $membership): ?MaaccRole => $membership->maacc_role)->filter()->unique();
        $permissions = $roles
            ->flatMap(fn (MaaccRole $role): array => $role->permissions())
            ->unique(fn (MaaccPermission $permission): string => $permission->value)
            ->values();

        return [
            'roles' => $roles->map(fn (MaaccRole $role): string => $role->value)->values()->all(),
            'permissions' => $permissions->map(fn (MaaccPermission $permission): string => $permission->value)->all(),
            'navigation' => $this->navigation($roles->all()),
            'projectIds' => $isPlatformAdmin ? [] : $memberships->pluck('project_id')->unique()->values()->all(),
            'isPlatformAdmin' => $isPlatformAdmin,
            'roleLabel' => $roles->isEmpty()
                ? 'No project access'
                : $roles->map(fn (MaaccRole $role): string => $role->label())->join(', '),
        ];
    }

    /**
     * Resolve navigation from the same roles used by backend policies.
     *
     * @param  array<int, MaaccRole>  $roles
     * @return array<int, string>
     */
    private function navigation(array $roles): array
    {
        $navigation = collect();

        foreach ($roles as $role) {
            $navigation->push(...match ($role) {
                MaaccRole::ProjectOwner => ['dashboard', 'applications', 'projects', 'agents', 'tools', 'sdk', 'journey', 'playground', 'connectors', 'knowledge', 'dataSources', 'evaluations', 'runs', 'governance', 'webhooks', 'routing', 'settings'],
                MaaccRole::Developer => ['dashboard', 'projects', 'agents', 'tools', 'sdk', 'journey', 'playground', 'knowledge', 'evaluations', 'runs', 'settings'],
                MaaccRole::Viewer => ['dashboard', 'projects', 'agents', 'tools', 'runs', 'settings'],
                MaaccRole::Auditor => ['dashboard', 'applications', 'projects', 'agents', 'tools', 'sdk', 'journey', 'runs', 'governance', 'settings'],
                MaaccRole::SecurityReviewer => ['dashboard', 'applications', 'projects', 'agents', 'tools', 'journey', 'connectors', 'knowledge', 'dataSources', 'runs', 'governance', 'settings'],
                MaaccRole::PlatformAdmin => [
                    'dashboard', 'applications', 'projects', 'agents', 'tools', 'sdk', 'journey',
                    'playground', 'connectors', 'knowledge', 'dataSources', 'evaluations', 'runs',
                    'llm', 'governance', 'webhooks', 'vault', 'routing', 'incidents', 'settings',
                ],
            });
        }

        return $navigation->unique()->values()->all();
    }
}
