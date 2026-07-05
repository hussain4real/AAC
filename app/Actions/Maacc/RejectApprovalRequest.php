<?php

namespace App\Actions\Maacc;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Runtime\AgentRunner;
use Illuminate\Support\Carbon;

/**
 * Rejects a governance approval request, recording the decision and reason
 * without applying the gated change. Rejecting a runtime approval additionally
 * fails the paused run it gated.
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

        if ($request->type === ApprovalType::RuntimeAction && $request->subject instanceof AgentRun) {
            app(AgentRunner::class)->denyRuntime($request->subject);
        }

        return $request;
    }
}
