<?php

namespace App\Http\Controllers\Maac;

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StorePlatformAccessRequest;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Write surface for MAAC platform-administration access control (Phase 8B): the
 * audited assign / revoke / break-glass / certify actions. The page itself is
 * rendered by {@see ConsoleController::accessControl()}. Routes are gated by
 * platform permissions; the two most sensitive grants (Super Admin, break-glass)
 * are further restricted to a Super Admin in {@see StorePlatformAccessRequest}.
 */
class PlatformAccessController extends Controller
{
    /**
     * Grant a platform role to a user (standard or break-glass).
     */
    public function store(StorePlatformAccessRequest $request, PlatformAccessManager $manager): RedirectResponse
    {
        $target = User::query()->whereKey((int) $request->validated('user_id'))->firstOrFail();
        $role = PlatformRole::from($request->validated('role'));
        $kind = PlatformAccessKind::from($request->validated('kind') ?? PlatformAccessKind::Standard->value);
        $reason = (string) $request->validated('reason');

        if ($kind === PlatformAccessKind::BreakGlass) {
            $ttl = $request->validated('ttl_minutes');
            $manager->breakGlass($target, $role, $request->user(), $reason, $ttl !== null ? (int) $ttl : null);
        } else {
            $manager->grant($target, $role, $request->user(), $reason);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform access granted.']);

        return back();
    }

    /**
     * Revoke a platform-role grant.
     */
    public function revoke(string $currentTeam, PlatformAccessGrant $grant, Request $request, PlatformAccessManager $manager): RedirectResponse
    {
        $this->guardSuperAdminGrant($request, $grant);

        $manager->revoke($grant, $request->user(), 'Revoked via console');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform access revoked.']);

        return back();
    }

    /**
     * Re-certify a platform-role grant during access review.
     */
    public function certify(string $currentTeam, PlatformAccessGrant $grant, Request $request, PlatformAccessManager $manager): RedirectResponse
    {
        $manager->certify($grant, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Access certified.']);

        return back();
    }

    /**
     * Only a Super Admin may revoke a Super Admin grant.
     */
    private function guardSuperAdminGrant(Request $request, PlatformAccessGrant $grant): void
    {
        abort_if(
            $grant->role === PlatformRole::SuperAdmin && ! $request->user()->isPlatformSuperAdmin(),
            403,
            'Only a Super Admin can revoke a Super Admin grant.',
        );
    }
}
