<?php

namespace App\Actions\Maacc;

use App\Enums\QuotaScope;
use App\Models\QuotaLimit;
use App\Models\Team;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateQuotaLimit
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Create a quota only after its subject is proven to belong to the team.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): QuotaLimit
    {
        $data = Arr::only($data, [
            'scope',
            'subject_id',
            'environment',
            'max_runs_per_day',
            'max_tokens_per_day',
            'enabled',
        ]);

        return DB::transaction(function () use ($team, $data): QuotaLimit {
            $scope = QuotaScope::from((string) $data['scope']);
            $subjectId = isset($data['subject_id']) ? (string) $data['subject_id'] : null;
            $this->relationships->assertQuotaSubject($team, $scope, $subjectId);

            return $team->quotaLimits()->create([
                ...$data,
                'subject_id' => $scope === QuotaScope::Platform ? null : $subjectId,
            ]);
        });
    }
}
