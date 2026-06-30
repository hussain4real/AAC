<?php

namespace App\Support\Platform;

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Models\AuditEvent;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Governs MAAC platform-role grants (Phase 8B).
 *
 * Every grant, break-glass activation, revocation, certification, and expiry is
 * applied to Spatie (the authorization source of truth) and recorded in the
 * {@see PlatformAccessGrant} ledger plus the audit log, so platform access is
 * always explainable: who holds which role, why, since when, and until when.
 */
class PlatformAccessManager
{
    /**
     * Grant a platform role to a user as a deliberate, certified assignment.
     */
    public function grant(User $target, PlatformRole $role, User $actor, string $reason): PlatformAccessGrant
    {
        return DB::transaction(function () use ($target, $role, $actor, $reason): PlatformAccessGrant {
            $target->assignRole($role->value);

            $grant = PlatformAccessGrant::create([
                'user_id' => $target->id,
                'role' => $role->value,
                'kind' => PlatformAccessKind::Standard->value,
                'reason' => $reason,
                'granted_by' => $actor->id,
                'certified_at' => now(),
                'certified_by' => $actor->id,
            ]);

            $this->audit($grant, $actor, 'platform_access.granted', [
                'role' => $role->value,
                'target' => $target->email,
            ]);

            return $grant;
        });
    }

    /**
     * Grant time-boxed emergency (break-glass) platform access that auto-expires.
     */
    public function breakGlass(User $target, PlatformRole $role, User $actor, string $reason, ?int $ttlMinutes = null): PlatformAccessGrant
    {
        $ttl = $this->resolveTtlMinutes($ttlMinutes);

        return DB::transaction(function () use ($target, $role, $actor, $reason, $ttl): PlatformAccessGrant {
            $target->assignRole($role->value);

            $grant = PlatformAccessGrant::create([
                'user_id' => $target->id,
                'role' => $role->value,
                'kind' => PlatformAccessKind::BreakGlass->value,
                'reason' => $reason,
                'granted_by' => $actor->id,
                'expires_at' => now()->addMinutes($ttl),
            ]);

            $this->audit($grant, $actor, 'platform_access.break_glass_activated', [
                'role' => $role->value,
                'target' => $target->email,
                'ttl_minutes' => $ttl,
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ]);

            return $grant;
        });
    }

    /**
     * Revoke a grant: mark it revoked and remove the Spatie role unless the user
     * still holds the same role through another active grant.
     */
    public function revoke(PlatformAccessGrant $grant, ?User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($grant, $actor, $reason): void {
            $grant->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor?->id,
                'reason' => $reason ?? $grant->reason,
            ])->save();

            if (! $this->hasOtherActiveGrant($grant)) {
                $grant->user->removeRole($grant->role->value);
            }

            $this->audit($grant, $actor, 'platform_access.revoked', [
                'role' => $grant->role->value,
                'target' => $grant->user->email,
            ]);
        });
    }

    /**
     * Idempotently grant a platform role from an SSO group claim. Returns the
     * created grant, or null when the user already holds the role (so repeated
     * logins do not spam the ledger). System-attributed (no human actor).
     */
    public function syncSsoRole(User $target, PlatformRole $role, string $source): ?PlatformAccessGrant
    {
        if ($target->hasRole($role->value)) {
            return null;
        }

        return DB::transaction(function () use ($target, $role, $source): PlatformAccessGrant {
            $target->assignRole($role->value);

            $grant = PlatformAccessGrant::create([
                'user_id' => $target->id,
                'role' => $role->value,
                'kind' => PlatformAccessKind::Standard->value,
                'reason' => 'Mapped from SSO group ('.$source.')',
                'certified_at' => now(),
            ]);

            $this->audit($grant, null, 'platform_access.granted', [
                'role' => $role->value,
                'target' => $target->email,
                'source' => 'sso:'.$source,
            ]);

            return $grant;
        });
    }

    /**
     * Re-certify a grant during access review (resets the certification clock).
     */
    public function certify(PlatformAccessGrant $grant, User $actor): void
    {
        $grant->forceFill(['certified_at' => now(), 'certified_by' => $actor->id])->save();

        $this->audit($grant, $actor, 'platform_access.certified', [
            'role' => $grant->role->value,
            'target' => $grant->user->email,
        ]);
    }

    /**
     * Revoke every break-glass grant whose window has elapsed. Returns the count
     * revoked (driven by the scheduled access-review command).
     */
    public function expireDueGrants(): int
    {
        $count = 0;

        PlatformAccessGrant::query()->dueForExpiry()->with('user')->get()
            ->each(function (PlatformAccessGrant $grant) use (&$count): void {
                $this->revoke($grant, null, 'Break-glass window elapsed');
                $this->audit($grant, null, 'platform_access.expired', ['role' => $grant->role->value]);
                $count++;
            });

        return $count;
    }

    /**
     * Active break-glass grants whose window has elapsed but are not yet revoked.
     *
     * @return Collection<int, PlatformAccessGrant>
     */
    public function dueForExpiry(): Collection
    {
        return PlatformAccessGrant::query()->dueForExpiry()->with('user')->get();
    }

    /**
     * Active standard grants that have not been certified within the configured
     * window (or never) — the periodic access-certification work list.
     *
     * @return Collection<int, PlatformAccessGrant>
     */
    public function needingCertification(): Collection
    {
        $threshold = now()->subDays($this->certificationDays());

        return PlatformAccessGrant::query()
            ->active()
            ->where('kind', PlatformAccessKind::Standard->value)
            ->where(fn ($query) => $query->whereNull('certified_at')->orWhere('certified_at', '<', $threshold))
            ->with('user')
            ->get();
    }

    /**
     * Active grants whose holder has had no audited platform activity within the
     * configured staleness window — candidate stale admin accounts.
     *
     * @return Collection<int, PlatformAccessGrant>
     */
    public function staleGrants(): Collection
    {
        $threshold = now()->subDays($this->staleDays());

        return PlatformAccessGrant::query()
            ->active()
            ->where('created_at', '<', $threshold)
            ->with('user')
            ->get()
            ->filter(function (PlatformAccessGrant $grant) use ($threshold): bool {
                $recentlyActive = AuditEvent::query()
                    ->where('actor_user_id', $grant->user_id)
                    ->where('created_at', '>=', $threshold)
                    ->exists();

                return ! $recentlyActive;
            })
            ->values();
    }

    /**
     * Whether the user still holds the grant's role through another active grant.
     */
    private function hasOtherActiveGrant(PlatformAccessGrant $grant): bool
    {
        return PlatformAccessGrant::query()
            ->where('user_id', $grant->user_id)
            ->where('role', $grant->role->value)
            ->whereKeyNot($grant->id)
            ->active()
            ->exists();
    }

    /**
     * Clamp the requested break-glass TTL to the configured default and hard max.
     */
    private function resolveTtlMinutes(?int $ttlMinutes): int
    {
        $default = (int) config('maac.platform.break_glass.default_ttl_minutes', 60);
        $max = (int) config('maac.platform.break_glass.max_ttl_minutes', 240);

        return min($ttlMinutes ?? $default, $max);
    }

    /**
     * The number of days a standard grant stays certified before review.
     */
    private function certificationDays(): int
    {
        return (int) config('maac.platform.access_review.certification_days', 90);
    }

    /**
     * The inactivity window (days) after which a platform admin is flagged stale.
     */
    private function staleDays(): int
    {
        return (int) config('maac.platform.access_review.stale_days', 60);
    }

    /**
     * Record a platform-access audit event, anchored to the actor's current team
     * (falling back to the grant holder's for system-driven expiry).
     *
     * @param  array<string, mixed>  $metadata
     */
    private function audit(PlatformAccessGrant $grant, ?User $actor, string $action, array $metadata): void
    {
        $actorTeamId = $actor?->current_team_id;
        $actorName = $actor?->name;

        AuditEvent::create([
            'team_id' => $actorTeamId ?? $grant->user->current_team_id,
            'actor_user_id' => $actor?->id,
            'actor_label' => $actorName ?? 'system',
            'action' => $action,
            'auditable_type' => $grant->getMorphClass(),
            'auditable_id' => $grant->id,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
