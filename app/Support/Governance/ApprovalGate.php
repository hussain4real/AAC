<?php

namespace App\Support\Governance;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\McpConnector;
use App\Models\ToolContract;

/**
 * Enforces approval due-diligence: a request may only be granted once its
 * prerequisites are met. The headline dependency is agent publication — an
 * agent cannot be published while its required tools are still awaiting
 * approval, are unimplemented in the target environment, or its model is not
 * approved there.
 */
class ApprovalGate
{
    /**
     * List the unmet prerequisites for the request (empty = ready to approve).
     *
     * @return array<int, string>
     */
    public function blockers(ApprovalRequest $request): array
    {
        return match ($request->type) {
            ApprovalType::AgentPublication => $this->agentBlockers($request),
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
            return [];
        }

        $environment = $request->environment ?? Environment::Production;
        $agent->loadMissing(['tools.mcpConnector', 'llmProvider']);
        $blockers = [];

        if (! $agent->llmProvider->isAvailableIn($environment->value)) {
            $blockers[] = "Model {$agent->llmProvider->name} is not approved for {$environment->label()}.";
        }

        foreach ($agent->tools as $tool) {
            if ($tool->requires_approval && $this->hasPendingToolApproval((int) $request->team_id, $tool)) {
                $blockers[] = "Tool {$tool->name} is still awaiting approval.";

                continue;
            }

            if ($tool->isClientSide() && ! $this->isImplemented($tool, $environment)) {
                $blockers[] = "Tool {$tool->name} has no implemented handler in {$environment->label()}.";
            }

            if ($tool->execution_mode === ExecMode::Connector && ! $this->connectorAvailable($tool, $environment)) {
                $blockers[] = "Tool {$tool->name} uses an MCP connector that is disabled or unavailable in {$environment->label()}.";
            }
        }

        return $blockers;
    }

    /**
     * Determine whether the connector backing an MCP tool is active and available
     * in the target environment.
     */
    private function connectorAvailable(ToolContract $tool, Environment $environment): bool
    {
        $connector = $tool->mcpConnector;

        return $connector instanceof McpConnector && $connector->isAvailableIn($environment->value);
    }

    /**
     * Determine whether a pending tool-contract approval exists for the tool.
     */
    private function hasPendingToolApproval(int $teamId, ToolContract $tool): bool
    {
        return ApprovalRequest::query()
            ->where('team_id', $teamId)
            ->where('type', ApprovalType::ToolContract)
            ->where('status', ApprovalStatus::Pending)
            ->where('subject_type', $tool->getMorphClass())
            ->where('subject_id', $tool->getKey())
            ->exists();
    }

    /**
     * Determine whether the tool has an implemented handler in the environment.
     */
    private function isImplemented(ToolContract $tool, Environment $environment): bool
    {
        return $tool->implementations()
            ->where('environment', $environment->value)
            ->where('status', ImplStatus::Implemented)
            ->exists();
    }
}
