<?php

namespace App\Support\Sso;

use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\SsoIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class SsoAccessIntegrityScanner
{
    /**
     * Produce a read-only, secret-free inventory of privileged SSO identities,
     * active web sessions, local authentication factors, and access-ledger drift.
     *
     * @return array{
     *     read_only: true,
     *     generated_at: string,
     *     session_cutoff: string,
     *     session_coverage: array{driver: string, supported: bool, available: bool, connection: string|null, table: string|null, reason: string|null},
     *     privileged_user_count: int,
     *     privileged_identity_count: int,
     *     active_session_count: int,
     *     finding_count: int,
     *     privileged_users: array<int, array<string, mixed>>,
     *     findings: array<int, array{code: string, severity: string, user_id: int, identity_id: string|null, message: string}>
     * }
     */
    public function scan(): array
    {
        $sessionCutoff = now()->subMinutes((int) config('session.lifetime', 120));
        $platformRoles = PlatformRole::values();
        $findings = [];
        $privilegedIdentityCount = 0;
        $activeSessionCount = 0;
        $sessionDriver = (string) config('session.driver', 'database');
        $configuredConnection = config('session.connection');
        $sessionConnection = is_string($configuredConnection) && $configuredConnection !== ''
            ? $configuredConnection
            : null;
        $sessionTable = (string) config('session.table', 'sessions');
        $sessionCoverage = [
            'driver' => $sessionDriver,
            'supported' => $sessionDriver === 'database',
            'available' => $sessionDriver === 'database',
            'connection' => $sessionConnection ?? (string) config('database.default'),
            'table' => $sessionDriver === 'database' ? $sessionTable : null,
            'reason' => $sessionDriver === 'database'
                ? null
                : "The {$sessionDriver} session driver cannot be inventoried by this database-backed audit.",
        ];

        $roleUserIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $platformRoles))
            ->pluck('id');
        $grantUserIds = PlatformAccessGrant::query()
            ->active()
            ->whereIn('role', $platformRoles)
            ->pluck('user_id');
        $privilegedUserIds = $roleUserIds
            ->merge($grantUserIds)
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $privilegedUserIds)
            ->with([
                'roles' => fn ($query) => $query->whereIn('name', $platformRoles),
                'ssoIdentities.connection',
            ])
            ->orderBy('id')
            ->get();

        $privilegedUsers = $users->map(function (User $user) use ($sessionCutoff, $sessionConnection, $sessionTable, &$sessionCoverage, &$findings, &$privilegedIdentityCount, &$activeSessionCount): array {
            $roles = $user->platformRoleValues();
            $identities = $user->ssoIdentities->map(function (SsoIdentity $identity) use ($user, &$findings, &$privilegedIdentityCount): array {
                $privilegedIdentityCount++;
                $connection = $identity->connection()->withTrashed()->first();

                $findings[] = $this->finding(
                    'privileged_sso_identity',
                    'critical',
                    $user,
                    $identity,
                    'A platform administrator is linked to a tenant SSO identity and requires Security/IAM review.',
                );

                if (strcasecmp((string) $identity->email, $user->email) !== 0) {
                    $findings[] = $this->finding(
                        'privileged_identity_email_mismatch',
                        'high',
                        $user,
                        $identity,
                        'The external identity email does not match the linked local account email.',
                    );
                }

                if ($connection === null || ! $connection->isActive()) {
                    $findings[] = $this->finding(
                        'privileged_identity_inactive_connection',
                        'high',
                        $user,
                        $identity,
                        'The privileged identity is attached to a connection that is not approved for login.',
                    );
                }

                return [
                    'identity_id' => $identity->id,
                    'connection_id' => $identity->sso_connection_id,
                    'connection_slug' => $connection?->slug,
                    'connection_team_id' => $connection?->team_id,
                    'connection_active' => $connection?->isActive() ?? false,
                    'issuer' => $connection?->issuer,
                    'subject_fingerprint' => hash('sha256', $identity->subject),
                    'email' => $identity->email,
                    'last_login_at' => $identity->last_login_at?->toIso8601String(),
                    'created_at' => $identity->created_at?->toIso8601String(),
                ];
            })->all();

            $sessions = [];

            if (! $sessionCoverage['supported']) {
                $findings[] = $this->finding(
                    'privileged_session_inventory_unsupported',
                    'high',
                    $user,
                    null,
                    "Active privileged sessions cannot be inventoried while the {$sessionCoverage['driver']} session driver is configured.",
                );
            } else {
                try {
                    $sessions = DB::connection($sessionConnection)
                        ->table($sessionTable)
                        ->where('user_id', $user->id)
                        ->where('last_activity', '>=', $sessionCutoff->getTimestamp())
                        ->orderByDesc('last_activity')
                        ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                        ->map(fn (object $session): array => [
                            'session_fingerprint' => hash('sha256', (string) $session->id),
                            'ip_address' => $session->ip_address,
                            'user_agent' => $session->user_agent,
                            'last_activity_at' => now()->setTimestamp((int) $session->last_activity)->toIso8601String(),
                        ])
                        ->all();
                } catch (Throwable) {
                    $sessionCoverage['available'] = false;
                    $sessionCoverage['reason'] = 'The configured database session inventory is unavailable.';
                    $findings[] = $this->finding(
                        'privileged_session_inventory_unavailable',
                        'high',
                        $user,
                        null,
                        'Active privileged sessions could not be read from the configured database session store.',
                    );
                }
            }

            $activeSessionCount += count($sessions);

            $hasPasskey = DB::table('passkeys')->where('user_id', $user->id)->exists();
            $hasConfirmedTotp = $user->two_factor_confirmed_at !== null;
            $hasRememberToken = is_string($user->remember_token) && $user->remember_token !== '';

            if (! $hasPasskey && ! $hasConfirmedTotp) {
                $findings[] = $this->finding(
                    'privileged_local_mfa_not_confirmed',
                    'high',
                    $user,
                    null,
                    'The platform administrator has no registered passkey or confirmed TOTP factor for local recovery.',
                );
            }

            if ($hasRememberToken) {
                $findings[] = $this->finding(
                    'privileged_remember_token_present',
                    'high',
                    $user,
                    null,
                    'The platform administrator has a persistent local remember token that must be revoked during access cleanup.',
                );
            }

            $activeLedgerGrants = PlatformAccessGrant::query()
                ->where('user_id', $user->id)
                ->active()
                ->whereIn('role', PlatformRole::values())
                ->get(['id', 'role']);
            $activeLedgerRoles = $activeLedgerGrants
                ->map(fn (PlatformAccessGrant $grant): string => $grant->role->value)
                ->unique()
                ->values()
                ->all();

            foreach (array_diff($roles, $activeLedgerRoles) as $role) {
                $findings[] = $this->finding(
                    'platform_role_without_active_grant',
                    'high',
                    $user,
                    null,
                    "The {$role} authorization role has no active platform-access ledger grant.",
                );
            }

            foreach (array_diff($activeLedgerRoles, $roles) as $role) {
                $findings[] = $this->finding(
                    'active_grant_without_platform_role',
                    'high',
                    $user,
                    null,
                    "The active {$role} platform-access ledger grant has no matching authorization role.",
                );
            }

            $activeLedgerGrants
                ->groupBy(fn (PlatformAccessGrant $grant): string => $grant->role->value)
                ->each(function ($grants, string $role) use ($user, &$findings): void {
                    if ($grants->count() > 1) {
                        $findings[] = $this->finding(
                            'duplicate_active_platform_grants',
                            'high',
                            $user,
                            null,
                            "The {$role} platform role has multiple active access-ledger grants.",
                        );
                    }
                });

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $roles,
                'local_factors' => [
                    'passkey_registered' => $hasPasskey,
                    'totp_confirmed' => $hasConfirmedTotp,
                    'remember_token_present' => $hasRememberToken,
                ],
                'sso_identities' => $identities,
                'active_sessions' => $sessions,
            ];
        })->all();

        return [
            'read_only' => true,
            'generated_at' => now()->toIso8601String(),
            'session_cutoff' => $sessionCutoff->toIso8601String(),
            'session_coverage' => $sessionCoverage,
            'privileged_user_count' => count($privilegedUsers),
            'privileged_identity_count' => $privilegedIdentityCount,
            'active_session_count' => $activeSessionCount,
            'finding_count' => count($findings),
            'privileged_users' => $privilegedUsers,
            'findings' => $findings,
        ];
    }

    /**
     * @return array{code: string, severity: string, user_id: int, identity_id: string|null, message: string}
     */
    private function finding(string $code, string $severity, User $user, ?SsoIdentity $identity, string $message): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'user_id' => $user->id,
            'identity_id' => $identity?->id,
            'message' => $message,
        ];
    }
}
