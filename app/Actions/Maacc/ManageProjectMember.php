<?php

namespace App\Actions\Maacc;

use App\Enums\MaaccRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Governance\AuditLedger;
use App\Support\MaaccConsoleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManageProjectMember
{
    /**
     * Assign or change a governed project access term.
     */
    public function assign(
        Project $project,
        User $subject,
        MaaccRole $role,
        User $actor,
        ?Carbon $expiresAt,
        string $reason,
    ): ProjectMember {
        return DB::transaction(function () use ($project, $subject, $role, $actor, $expiresAt, $reason): ProjectMember {
            $membership = ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('user_id', $subject->id)
                ->lockForUpdate()
                ->first();
            $previousRole = $membership?->maacc_role;
            $action = $membership === null || ! $membership->isActive()
                ? 'project_member.assigned'
                : 'project_member.role_changed';

            $membership ??= new ProjectMember([
                'project_id' => $project->id,
                'user_id' => $subject->id,
            ]);
            $membership->forceFill([
                'maacc_role' => $role,
                'granted_by_user_id' => $actor->id,
                'revoked_by_user_id' => null,
                'certified_by_user_id' => null,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
                'certified_at' => null,
                'reason' => $reason,
                'certification_note' => null,
            ])->save();

            $this->audit($project, $membership, $actor, $action, [
                'subject_user_id' => $subject->id,
                'previous_role' => $previousRole?->value,
                'role' => $role->value,
                'expires_at' => $expiresAt?->toIso8601String(),
                'reason' => $reason,
            ]);

            return $membership->fresh(['user', 'grantor', 'revoker', 'certifier']);
        });
    }

    /**
     * Revoke a project access term without deleting its evidence.
     */
    public function revoke(ProjectMember $membership, User $actor, string $reason): ProjectMember
    {
        return DB::transaction(function () use ($membership, $actor, $reason): ProjectMember {
            $membership = ProjectMember::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();

            if ($membership->revoked_at === null) {
                $membership->forceFill([
                    'revoked_by_user_id' => $actor->id,
                    'revoked_at' => now(),
                    'reason' => $reason,
                ])->save();

                $this->audit($membership->project, $membership, $actor, 'project_member.revoked', [
                    'subject_user_id' => $membership->user_id,
                    'role' => $membership->maacc_role?->value,
                    'reason' => $reason,
                ]);
            }

            return $membership->fresh(['user', 'grantor', 'revoker', 'certifier']);
        });
    }

    /**
     * Record an access review for an active project access term.
     */
    public function certify(ProjectMember $membership, User $actor, string $note): ProjectMember
    {
        return DB::transaction(function () use ($membership, $actor, $note): ProjectMember {
            $membership = ProjectMember::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($membership->isActive(), 409, 'Only active project access can be certified.');

            $membership->forceFill([
                'certified_by_user_id' => $actor->id,
                'certified_at' => now(),
                'certification_note' => $note,
            ])->save();

            $this->audit($membership->project, $membership, $actor, 'project_member.certified', [
                'subject_user_id' => $membership->user_id,
                'role' => $membership->maacc_role?->value,
                'note' => $note,
            ]);

            return $membership->fresh(['user', 'grantor', 'revoker', 'certifier']);
        });
    }

    /**
     * Persist an attributable evidence event for a membership lifecycle change.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Project $project, ProjectMember $membership, User $actor, string $action, array $metadata): void
    {
        $project->loadMissing('application');

        app(AuditLedger::class)->record([
            'team_id' => $project->application->team_id,
            'actor_user_id' => $actor->id,
            'actor_label' => $actor->name,
            'action' => $action,
            'auditable_type' => ProjectMember::class,
            'auditable_id' => (string) $membership->getKey(),
            'environment' => $project->environment,
            'metadata' => ['project_id' => $project->id, ...$metadata],
            'ip_address' => request()->ip(),
        ]);

        DB::afterCommit(fn () => MaaccConsoleData::invalidate());
    }
}
