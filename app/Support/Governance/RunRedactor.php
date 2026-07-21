<?php

namespace App\Support\Governance;

use App\Enums\PayloadHandling;
use App\Enums\Sensitivity;
use App\Models\AgentRun;
use App\Models\GovernanceSetting;

/**
 * Applies a team's masking/retention policy to a specific run's payloads,
 * resolving the governance settings for the run's team and environment. Used by
 * the runtime to redact prompts, responses, and tool payloads at the storage
 * boundary.
 */
class RunRedactor
{
    /**
     * Per-team governance settings resolved within this request.
     *
     * @var array<int, GovernanceSetting>
     */
    private array $settingsCache = [];

    public function __construct(private readonly PayloadMasker $masker) {}

    /**
     * Redact a run input/prompt for storage. The masking policy treats the
     * prompt as an input payload.
     */
    public function input(AgentRun $run, ?string $value): ?string
    {
        return $this->masker->maskText($value, $this->masksInputs($run), $this->sensitivity($run), $this->blocks($run));
    }

    /**
     * Redact a model response before it crosses a retained storage boundary.
     */
    public function output(AgentRun $run, ?string $value): ?string
    {
        return $this->masker->maskText($value, $this->masksOutputs($run), $this->sensitivity($run), $this->blocks($run));
    }

    /**
     * Redact tool arguments before they are persisted or emitted.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public function arguments(AgentRun $run, ?array $value): ?array
    {
        return $this->masker->maskArray($value, $this->masksInputs($run), $this->sensitivity($run), $this->blocks($run));
    }

    /**
     * Redact a tool-call result for storage (the sensitive data the tool
     * returns), treated as an output payload.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public function result(AgentRun $run, ?array $value): ?array
    {
        $handling = $this->settings($run)->toolResultHandling($run->environment);

        if ($handling === PayloadHandling::Exclude) {
            return null;
        }

        return $this->masker->maskArray(
            $value,
            $handling === PayloadHandling::Mask,
            $this->sensitivity($run),
            $this->blocks($run),
        );
    }

    /**
     * Determine whether any payload on the run would be masked or blocked.
     */
    public function applies(AgentRun $run): bool
    {
        return $this->masker->wouldRedact(
            $this->sensitivity($run),
            $this->masksInputs($run) || $this->masksOutputs($run),
            $this->blocks($run),
        );
    }

    /**
     * Whether sensitive inputs are masked for the run's environment.
     */
    private function masksInputs(AgentRun $run): bool
    {
        return $this->settings($run)->masksInputs($run->environment);
    }

    /**
     * Whether sensitive outputs are masked for the run's environment.
     */
    private function masksOutputs(AgentRun $run): bool
    {
        return $this->settings($run)->masksOutputs($run->environment);
    }

    /**
     * Whether restricted payloads are blocked for the run's environment.
     */
    private function blocks(AgentRun $run): bool
    {
        return $this->settings($run)->blocksRestrictedLogging($run->environment);
    }

    /**
     * Treat legacy runs without an explicit classification as Internal.
     */
    private function sensitivity(AgentRun $run): Sensitivity
    {
        return $run->sensitivity;
    }

    /**
     * Resolve (and cache) the governance settings for the run's team.
     */
    private function settings(AgentRun $run): GovernanceSetting
    {
        $run->loadMissing('application.team');
        $team = $run->application->team;

        return $this->settingsCache[$team->id] ??= GovernanceSetting::forTeam($team);
    }
}
