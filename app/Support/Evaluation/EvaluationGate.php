<?php

namespace App\Support\Evaluation;

use App\Models\Agent;
use App\Models\Evaluation;

/**
 * The promotion gate: an agent may not be published while a dataset it has
 * flagged as a required evaluation has not most-recently passed. Datasets the
 * agent has never marked required do not gate it — the gate is opt-in per agent.
 */
class EvaluationGate
{
    /**
     * List the unmet evaluation prerequisites for publishing the agent (empty =
     * ready). Only the latest required evaluation of each dataset is considered.
     *
     * @return array<int, string>
     */
    public function blockers(Agent $agent): array
    {
        $seen = [];
        $blockers = [];

        /** @var Evaluation $evaluation */
        foreach ($agent->evaluations()->required()->latest()->get() as $evaluation) {
            if (in_array($evaluation->evaluation_dataset_id, $seen, true)) {
                continue;
            }

            $seen[] = $evaluation->evaluation_dataset_id;

            if (! $evaluation->hasPassed()) {
                $blockers[] = "Required evaluation \"{$evaluation->label}\" has not passed (status: {$evaluation->status->label()}).";
            }
        }

        return $blockers;
    }

    /**
     * Whether the agent has cleared every required evaluation.
     */
    public function isSatisfied(Agent $agent): bool
    {
        return $this->blockers($agent) === [];
    }
}
