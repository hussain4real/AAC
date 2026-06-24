<?php

namespace App\Support\Evaluation;

use App\Enums\Environment;
use App\Enums\EvaluationStatus;
use App\Enums\RunMode;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Evaluation;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use App\Models\EvaluationResult;
use App\Models\User;
use App\Support\Runtime\AgentRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Runs a golden dataset against an agent. For each case it drives a real agent
 * run through the {@see AgentRunner} (servicing any client-side tool pause from
 * the case's stubbed result), grades the run with the {@see EvaluationGrader},
 * and persists a per-case result. It then rolls the results up onto the
 * evaluation (pass/correctness/safety/citation rates, cost, latency) and marks
 * it passed or failed — the verdict a promotion gate reads. Evaluation runs are
 * driven against the candidate agent even before it is published, so a version
 * can be assessed before promotion.
 */
class EvaluationRunner
{
    /**
     * The maximum client-side tool round-trips to service per case (a guard
     * against a stub loop; a normal case needs one or none).
     */
    private const MAX_TOOL_TURNS = 12;

    public function __construct(
        private readonly AgentRunner $runner,
        private readonly EvaluationGrader $grader,
    ) {}

    /**
     * Run the dataset against the agent and return the completed evaluation.
     */
    public function run(EvaluationDataset $dataset, Agent $agent, User $creator, Environment $environment, bool $isRequired): Evaluation
    {
        $agent->loadMissing(['tools', 'llmProvider', 'project.application']);
        $application = $agent->project->application;

        $evaluation = Evaluation::create([
            'team_id' => $application->team_id,
            'evaluation_dataset_id' => $dataset->id,
            'agent_id' => $agent->id,
            'agent_version_id' => $agent->current_version_id,
            'environment' => $environment,
            'label' => Str::limit($dataset->name.' · '.$agent->name, 200, ''),
            'status' => EvaluationStatus::Running,
            'is_required' => $isRequired,
            'agent_version' => $agent->version,
            'model_code' => $agent->llmProvider->code,
            'prompt_fingerprint' => substr(hash('sha256', $agent->system_prompt), 0, 16),
            'started_at' => Date::now(),
            'created_by' => $creator->id,
        ]);

        foreach ($dataset->cases as $case) {
            $this->runCase($evaluation, $agent, $application, $environment, $case);
        }

        return $this->finalize($evaluation);
    }

    /**
     * Drive, grade, and record a single case.
     */
    private function runCase(Evaluation $evaluation, Agent $agent, Application $application, Environment $environment, EvaluationCase $case): void
    {
        $run = $this->driveRun($evaluation, $agent, $application, $environment, $case);
        $run->load('toolCalls');

        $graded = $this->grader->grade($case, $run);

        $evaluation->results()->create([
            'evaluation_case_id' => $case->id,
            'agent_run_id' => $run->id,
            'case_name' => $case->name,
            'kind' => $case->kind,
            'passed' => $graded['passed'],
            'checks' => $graded['checks'],
            'citations' => $graded['citations'] === [] ? null : $graded['citations'],
            'cost' => $run->cost,
            'latency_ms' => (int) ($run->latency_ms ?? 0),
            'output' => $graded['output'],
            'failure_reason' => $graded['failure_reason'],
        ]);
    }

    /**
     * Create and drive the run for a case, feeding back the case's stubbed
     * results when the run pauses for a client-side tool.
     */
    private function driveRun(Evaluation $evaluation, Agent $agent, Application $application, Environment $environment, EvaluationCase $case): AgentRun
    {
        $run = $this->runner->createRun($agent, $application, $environment, $case->input, 'evaluation:'.$evaluation->id, RunMode::Sync);
        $run->update(['evaluation_id' => $evaluation->id]);
        $run = $this->runner->process($run);

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS && $run->isWaitingForClient(); $turn++) {
            $call = $run->pendingToolCalls()->first();
            $stub = $call?->tool_name !== null ? $case->toolStub($call->tool_name) : null;

            if ($call === null || $stub === null) {
                break;
            }

            try {
                $run = $this->runner->resume($run, $call->id, $stub);
            } catch (RuntimeRequestException) {
                // An invalid/oversized stub fails the run boundary; leave the run
                // unresolved so the grader records the case as failed.
                break;
            }
        }

        return $run;
    }

    /**
     * Roll the per-case results up onto the evaluation and mark it passed/failed.
     */
    private function finalize(Evaluation $evaluation): Evaluation
    {
        $results = $evaluation->results()->get();
        $total = $results->count();
        $passed = $results->where('passed', true)->count();

        $evaluation->update([
            'status' => ($total === 0 || $passed === $total) ? EvaluationStatus::Passed : EvaluationStatus::Failed,
            'cases_total' => $total,
            'cases_passed' => $passed,
            'pass_rate' => $total === 0 ? 100 : round($passed / $total * 100, 2),
            'total_cost' => round((float) $results->sum('cost'), 6),
            'avg_latency_ms' => $total === 0 ? 0 : (int) round((float) $results->avg('latency_ms')),
            'correctness_rate' => $this->checkRate($results, 'correctness'),
            'safety_rate' => $this->checkRate($results, 'safety'),
            'citation_rate' => $this->checkRate($results, 'citation'),
            'completed_at' => Date::now(),
        ]);

        return $evaluation->refresh();
    }

    /**
     * The pass rate (0–100) for a given check type across the results that
     * declare it (100 when no result exercises the check).
     *
     * @param  Collection<int, EvaluationResult>  $results
     */
    private function checkRate(Collection $results, string $type): float
    {
        $relevant = $results->filter(
            fn ($result): bool => collect($result->checks)->contains(fn (array $check): bool => $check['type'] === $type),
        );

        if ($relevant->isEmpty()) {
            return 100;
        }

        $ok = $relevant->filter(
            fn ($result): bool => collect($result->checks)
                ->where('type', $type)
                ->every(fn (array $check): bool => $check['passed'] === true),
        )->count();

        return round($ok / $relevant->count() * 100, 2);
    }
}
