<?php

namespace App\Support\Runtime\Routing;

use App\Enums\RoutingStrategy;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use Illuminate\Support\Collection;

/**
 * Selects the model a run executes on. With no routing policy the run uses the
 * agent's configured model (preserving the original behavior); with a policy it
 * ranks the policy's candidate chain — filtering by environment availability,
 * sensitivity clearance, cost ceiling, latency target, and recent provider
 * health — and orders the survivors by the policy strategy. The decision is pure:
 * the runtime applies it (switching the run's model and tracing the rationale).
 */
class ModelRouter
{
    public function __construct(private readonly ProviderHealth $health) {}

    /**
     * Resolve the routing decision for the given run.
     */
    public function select(AgentRun $run): RoutingDecision
    {
        $agent = $run->agent;
        $policy = $agent->routingPolicy;

        if (! $policy instanceof ModelRoutingPolicy || ! $policy->enabled) {
            return $this->withoutPolicy($run, $agent);
        }

        return $this->withPolicy($run, $agent, $policy);
    }

    /**
     * The default decision: use the agent's configured model when it is approved
     * and available in the run environment, otherwise no model.
     */
    private function withoutPolicy(AgentRun $run, Agent $agent): RoutingDecision
    {
        $provider = $agent->llmProvider;
        $available = $run->environment !== null
            && $provider->isVerified()
            && $provider->isAvailableIn($run->environment->value)
            && $this->hasCredentialSource($provider)
            && $agent->project->llmProviders()->whereKey($provider->id)->exists();

        $evaluation = new CandidateEvaluation(
            $provider,
            $available,
            $available ? 'Selected (agent default).' : 'Not approved or available in the environment.',
            CandidateEvaluation::costOf($provider),
            null,
            true,
        );

        return new RoutingDecision(
            $available ? $provider : null,
            $available ? [$provider] : [],
            [$evaluation],
            null,
            $available
                ? "No routing policy; using the agent's configured model."
                : 'The agent model is not approved or available in this environment.',
        );
    }

    /**
     * Apply the routing policy: evaluate, filter, and order the candidate chain.
     */
    private function withPolicy(AgentRun $run, Agent $agent, ModelRoutingPolicy $policy): RoutingDecision
    {
        $candidateIds = $policy->candidateProviderIds();

        $providers = $agent->project->llmProviders()
            ->where('llm_providers.team_id', $agent->project->application->team_id)
            ->whereIn('llm_providers.id', $candidateIds)
            ->get()
            ->keyBy('id');

        /** @var Collection<int, LlmProvider> $ordered */
        $ordered = collect($candidateIds)
            ->map(fn (string $id): ?LlmProvider => $providers->get($id))
            ->filter()
            ->values();

        $health = $this->health->forProviderIds($ordered->pluck('id')->all(), $run->environment);

        $considered = $ordered
            ->map(fn (LlmProvider $provider): CandidateEvaluation => $this->evaluate(
                $run,
                $policy,
                $provider,
                $health[$provider->id] ?? ProviderHealthSnapshot::unknown(),
            ))
            ->all();

        $eligible = $this->order(
            array_values(array_filter($considered, fn (CandidateEvaluation $candidate): bool => $candidate->eligible)),
            $policy->strategy,
        );

        $chosen = $eligible[0] ?? null;

        $rationale = $chosen instanceof LlmProvider
            ? sprintf('Selected %s via %s; %d of %d candidate(s) eligible.', $chosen->code, $policy->strategy->label(), count($eligible), count($considered))
            : 'No candidate model is eligible for this run.';

        return new RoutingDecision($chosen, $eligible, $considered, $policy->strategy, $rationale);
    }

    /**
     * Evaluate one candidate against the policy and run constraints, returning the
     * first failing reason or eligibility.
     */
    private function evaluate(AgentRun $run, ModelRoutingPolicy $policy, LlmProvider $provider, ProviderHealthSnapshot $health): CandidateEvaluation
    {
        $cost = CandidateEvaluation::costOf($provider);
        $make = fn (bool $eligible, string $reason): CandidateEvaluation => new CandidateEvaluation(
            $provider, $eligible, $reason, $cost, $health->avgLatencyMs, $health->healthy,
        );

        if ($run->environment === null || ! $provider->isAvailableIn($run->environment->value)) {
            return $make(false, 'Not approved or available in the environment.');
        }

        if (! $provider->isVerified()) {
            return $make(false, 'The provider has not passed verification.');
        }

        if (! $this->hasCredentialSource($provider)) {
            return $make(false, 'The provider has no approved credential source.');
        }

        if (! $provider->sensitivity->isAtLeast($run->sensitivity)) {
            return $make(false, "Not cleared for {$run->sensitivity->label()} data.");
        }

        if ($policy->max_cost_per_1k !== null && $cost > $policy->max_cost_per_1k) {
            return $make(false, 'Exceeds the policy cost ceiling.');
        }

        if ($policy->max_latency_ms !== null && $health->avgLatencyMs !== null && $health->avgLatencyMs > $policy->max_latency_ms) {
            return $make(false, 'Exceeds the policy latency target.');
        }

        if (! $health->healthy) {
            return $make(false, 'Provider is unhealthy (recent failure rate too high).');
        }

        return $make(true, 'Eligible.');
    }

    /**
     * Tenant-managed providers fail closed until a vault key is bound.
     */
    private function hasCredentialSource(LlmProvider $provider): bool
    {
        return $provider->platform_owned || $provider->vault_secret_id !== null;
    }

    /**
     * Order the eligible candidates by the policy strategy and return their
     * providers (the run's fail-over chain).
     *
     * @param  array<int, CandidateEvaluation>  $eligible
     * @return array<int, LlmProvider>
     */
    private function order(array $eligible, RoutingStrategy $strategy): array
    {
        usort($eligible, fn (CandidateEvaluation $a, CandidateEvaluation $b): int => match ($strategy) {
            RoutingStrategy::CostOptimized => $a->totalCost <=> $b->totalCost,
            RoutingStrategy::LatencyOptimized => ($a->avgLatencyMs ?? PHP_INT_MAX) <=> ($b->avgLatencyMs ?? PHP_INT_MAX),
            RoutingStrategy::Balanced => [$a->totalCost, $a->avgLatencyMs ?? PHP_INT_MAX] <=> [$b->totalCost, $b->avgLatencyMs ?? PHP_INT_MAX],
        });

        return array_map(fn (CandidateEvaluation $candidate): LlmProvider => $candidate->provider, $eligible);
    }
}
