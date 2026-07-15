<?php

namespace App\Actions\Maacc;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\CredentialStatus;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\User;
use App\Support\Runtime\AgentRunner;
use App\Support\Sdk\SdkClientManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rejects a governance approval request, recording the decision and reason
 * without applying the gated change. Rejecting a runtime approval additionally
 * fails the paused run it gated.
 */
class RejectApprovalRequest
{
    public function __construct(private readonly SdkClientManager $sdkClients) {}

    /**
     * Reject the request.
     */
    public function handle(ApprovalRequest $request, User $decider, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $decider, $note): ApprovalRequest {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $locked->isPending()) {
                return $locked;
            }

            $locked->update([
                'status' => ApprovalStatus::Rejected,
                'pending_key' => null,
                'decided_by' => $decider->id,
                'decided_label' => $decider->name,
                'decision_note' => $note,
                'decided_at' => Carbon::now(),
            ]);

            if ($locked->type === ApprovalType::RuntimeAction && $locked->subject instanceof AgentRun) {
                app(AgentRunner::class)->denyRuntime($locked->subject);
            }

            if ($locked->type === ApprovalType::CredentialChange && $locked->subject instanceof Credential) {
                $credential = $locked->subject;

                if (($locked->metadata ?? [])['change'] === 'creation') {
                    $this->sdkClients->revoke($credential);
                    $credential->update([
                        'status' => CredentialStatus::Revoked,
                        'revoked_at' => Carbon::now(),
                    ]);
                }

                $locked->update(['encrypted_payload' => null]);
            }

            return $locked;
        });
    }
}
