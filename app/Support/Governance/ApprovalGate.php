<?php

namespace App\Support\Governance;

use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Enforces approval due-diligence: a request may only be granted once its
 * prerequisites are met. The headline dependency is agent publication — an
 * agent cannot be published while its required tools are still awaiting
 * approval, are unimplemented in the target environment, its model is not
 * approved there, or a required evaluation has not passed.
 */
class ApprovalGate
{
    public function __construct(
        private readonly AgentReadinessGate $readiness,
        private readonly ApprovalVersion $versions,
    ) {}

    /**
     * List the unmet prerequisites for the request (empty = ready to approve).
     *
     * @return array<int, string>
     */
    public function blockers(ApprovalRequest $request): array
    {
        if (! $this->hasExpectedSubject($request)) {
            return ['The approval subject no longer exists.'];
        }

        if ($this->subjectTeamId($request->subject) !== $request->team_id) {
            return ['The approval subject does not belong to this tenant.'];
        }

        return match ($request->type) {
            ApprovalType::AgentPublication => $this->agentBlockers($request),
            ApprovalType::ModelAccess => $this->modelBlockers($request),
            ApprovalType::CredentialChange => $this->credentialBlockers($request),
            default => [],
        };
    }

    /**
     * Determine whether the request has no unmet prerequisites.
     */
    public function isSatisfied(ApprovalRequest $request): bool
    {
        return $this->blockers($request) === [];
    }

    /**
     * Assert the request is ready to approve, throwing otherwise.
     *
     * @throws ApprovalBlockedException
     */
    public function ensureSatisfied(ApprovalRequest $request): void
    {
        $blockers = $this->blockers($request);

        if ($blockers !== []) {
            throw new ApprovalBlockedException($blockers);
        }
    }

    /**
     * Prerequisites for publishing an agent into the request's environment.
     *
     * @return array<int, string>
     */
    private function agentBlockers(ApprovalRequest $request): array
    {
        $agent = $request->subject;

        if (! $agent instanceof Agent) {
            return ['The approval subject no longer exists.'];
        }

        $environment = $request->environment ?? Environment::Production;
        $agent->loadMissing('project.application');

        $blockers = $this->readiness->blockers(
            $agent,
            $agent->project->application,
            $environment,
            requirePublished: false,
            requireEvaluations: true,
            requireImmutableVersion: false,
        );

        if (! $this->readiness->approvalIsCurrent($request)) {
            $blockers[] = 'The agent configuration changed after this approval was requested.';
        }

        return $blockers;
    }

    /**
     * Ensure a production credential proposal is complete and still current.
     *
     * @return array<int, string>
     */
    private function credentialBlockers(ApprovalRequest $request): array
    {
        $credential = $request->subject;

        if (! $credential instanceof Credential) {
            return ['The approval subject no longer exists.'];
        }

        if ($credential->application->team_id !== $request->team_id) {
            return ['The approval subject does not belong to this tenant.'];
        }

        if (! is_string($request->subject_version_hash)
            || ! hash_equals($request->subject_version_hash, $this->versions->credential($credential))) {
            return ['The credential changed after this approval was requested.'];
        }

        $change = ($request->metadata ?? [])['change'] ?? null;

        if (! in_array($change, ['creation', 'rotation'], true)) {
            return ['The staged credential change is invalid.'];
        }

        if ($change === 'rotation' && ! is_string(($request->encrypted_payload ?? [])['secret'] ?? null)) {
            return ['The staged credential secret is unavailable.'];
        }

        return [];
    }

    /**
     * Ensure a model promotion is verified, credentialed, and unchanged.
     *
     * @return array<int, string>
     */
    private function modelBlockers(ApprovalRequest $request): array
    {
        $model = $request->subject;

        if (! $model instanceof LlmProvider || $request->environment === null) {
            return ['The staged model promotion is invalid.'];
        }

        $blockers = [];

        if (! $model->isVerified()) {
            $blockers[] = 'The model no longer has a successful verification.';
        }

        if (! $model->platform_owned && $model->vault_secret_id === null) {
            $blockers[] = 'The model has no approved credential source.';
        }

        if (! is_string($request->subject_version_hash)
            || ! hash_equals($request->subject_version_hash, $this->versions->model($model))) {
            $blockers[] = 'The model changed after this approval was requested.';
        }

        return $blockers;
    }

    /**
     * Confirm the polymorphic subject matches the approval category.
     */
    private function hasExpectedSubject(ApprovalRequest $request): bool
    {
        return match ($request->type) {
            ApprovalType::AgentPublication => $request->subject instanceof Agent,
            ApprovalType::ToolContract => $request->subject instanceof ToolContract,
            ApprovalType::ModelAccess => $request->subject instanceof LlmProvider,
            ApprovalType::CredentialChange => $request->subject instanceof Credential,
            ApprovalType::KnowledgeIngestion => $request->subject instanceof KnowledgeSource,
            ApprovalType::DataSourceAccess => $request->subject instanceof DataSource,
            ApprovalType::RuntimeAction => $request->subject instanceof AgentRun,
        };
    }

    /**
     * Resolve the authoritative tenant for any governed subject.
     */
    private function subjectTeamId(Model $subject): int
    {
        return match (true) {
            $subject instanceof Agent => $subject->project->application->team_id,
            $subject instanceof Credential => $subject->application->team_id,
            $subject instanceof AgentRun => $subject->application->team_id,
            $subject instanceof ToolContract,
            $subject instanceof LlmProvider,
            $subject instanceof KnowledgeSource,
            $subject instanceof DataSource => $subject->team_id,
            default => throw new LogicException('Unsupported approval subject type.'),
        };
    }
}
