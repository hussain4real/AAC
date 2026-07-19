<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\EnterpriseReadiness;
use App\Support\MaaccAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'readiness' => fn (): array => app(EnterpriseReadiness::class)->sharedState(),
            'auth' => [
                'user' => $user,
                // The user's MAACC platform-administration access (Phase 8B), so
                // the console can gate platform-admin nav and controls on the
                // real global RBAC rather than the front-end persona mock.
                'platform' => fn (): array => $this->platformAccess($user),
                'maacc' => fn (): array => $user?->currentTeam
                    ? app(MaaccAccess::class)->forUser($user, $user->currentTeam)
                    : ['roles' => [], 'permissions' => [], 'navigation' => [], 'projectIds' => [], 'isPlatformAdmin' => false, 'roleLabel' => 'No access'],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }

    /**
     * The current user's MAACC platform-administration access snapshot for the
     * client (empty for a guest or a pure tenant user).
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>, isSuperAdmin: bool, isAdministrator: bool}
     */
    private function platformAccess(?User $user): array
    {
        if ($user === null) {
            return ['roles' => [], 'permissions' => [], 'isSuperAdmin' => false, 'isAdministrator' => false];
        }

        return [
            'roles' => $user->platformRoleValues(),
            'permissions' => $user->platformPermissionValues(),
            'isSuperAdmin' => $user->isPlatformSuperAdmin(),
            'isAdministrator' => $user->isPlatformAdministrator(),
        ];
    }
}
