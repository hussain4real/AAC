<?php

namespace App\Actions\Maacc;

use App\Enums\QuotaScope;
use App\Models\QuotaLimit;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateQuotaLimit
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Update a quota without allowing it to target a foreign-team subject.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(QuotaLimit $quotaLimit, array $data): QuotaLimit
    {
        $data = Arr::only($data, [
            'scope',
            'subject_id',
            'environment',
            'max_runs_per_day',
            'max_tokens_per_day',
            'enabled',
        ]);

        return DB::transaction(function () use ($quotaLimit, $data): QuotaLimit {
            $quotaLimit = $this->relationships->quotaLimitForWrite($quotaLimit);
            $scope = array_key_exists('scope', $data)
                ? QuotaScope::from((string) $data['scope'])
                : $quotaLimit->scope;
            $subjectId = array_key_exists('subject_id', $data)
                ? (is_string($data['subject_id']) ? $data['subject_id'] : null)
                : ($scope === QuotaScope::Platform ? null : $quotaLimit->subject_id);

            $this->relationships->assertQuotaSubject($quotaLimit->team, $scope, $subjectId);

            $quotaLimit->update([
                ...$data,
                'subject_id' => $scope === QuotaScope::Platform ? null : $subjectId,
            ]);

            return $quotaLimit;
        });
    }
}
