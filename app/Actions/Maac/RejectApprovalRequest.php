<?php

namespace App\Actions\Maac;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Rejects a governance approval request, recording the decision and reason
 * without applying the gated change.
 */
class RejectApprovalRequest
{
    /**
     * Reject the request.
     */
    public function handle(ApprovalRequest $request, User $decider, ?string $note = null): ApprovalRequest
    {
        $request->update([
            'status' => ApprovalStatus::Rejected,
            'decided_by' => $decider->id,
            'decided_label' => $decider->name,
            'decision_note' => $note,
            'decided_at' => Carbon::now(),
        ]);

        return $request;
    }
}
