<?php

namespace App\Support\Evaluation;

use App\Enums\ExecMode;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\EvaluationCase;
use App\Models\ToolCall;
use Illuminate\Support\Str;

/**
 * Grades a single evaluation case against the agent run it produced. It records
 * a per-check verdict for each assertion the case declares — completion,
 * correctness (expected text), tool usage, citation presence, safety (forbidden
 * phrases), and cost/latency ceilings — and reports the citations the run
 * surfaced. A case passes only when the run completed and every applicable
 * check passed.
 */
class EvaluationGrader
{
    /**
     * Grade the case against its run.
     *
     * @return array{passed: bool, checks: array<int, array{type: string, passed: bool, detail: string}>, citations: array<int, array<string, mixed>>, failure_reason: string|null, output: string}
     */
    public function grade(EvaluationCase $case, AgentRun $run): array
    {
        $expectations = $case->expectations;
        $output = (string) ($run->output ?? '');
        $citations = $this->extractCitations($run);

        $completed = $run->status === RunStatus::Completed;
        $checks = [[
            'type' => 'completion',
            'passed' => $completed,
            'detail' => $completed ? 'Run completed.' : "Run did not complete (status: {$run->status->value}).",
        ]];

        foreach ($this->stringList($expectations, 'expected_contains') as $needle) {
            $hit = Str::contains(Str::lower($output), Str::lower($needle));
            $checks[] = ['type' => 'correctness', 'passed' => $hit, 'detail' => $hit ? "Answer contained \"{$needle}\"." : "Answer did not contain \"{$needle}\"."];
        }

        $expectedTool = $this->stringValue($expectations, 'expected_tool');

        if ($expectedTool !== null) {
            $called = in_array($expectedTool, $run->tools ?? [], true);
            $checks[] = ['type' => 'tool', 'passed' => $called, 'detail' => $called ? "Tool {$expectedTool} was called." : "Tool {$expectedTool} was not called."];
        }

        if (($expectations['expects_citation'] ?? false) === true) {
            $hasCitation = $citations !== [];
            $checks[] = ['type' => 'citation', 'passed' => $hasCitation, 'detail' => $hasCitation ? count($citations).' citation(s) surfaced.' : 'No citation was surfaced.'];
        }

        foreach ($this->stringList($expectations, 'forbidden_phrases') as $phrase) {
            $clean = ! Str::contains(Str::lower($output), Str::lower($phrase));
            $checks[] = ['type' => 'safety', 'passed' => $clean, 'detail' => $clean ? "Answer avoided \"{$phrase}\"." : "Answer contained forbidden \"{$phrase}\"."];
        }

        if (($max = $this->floatValue($expectations, 'max_cost')) !== null) {
            $ok = $run->cost <= $max;
            $checks[] = ['type' => 'cost', 'passed' => $ok, 'detail' => "Run cost {$run->cost} against a ceiling of {$max}."];
        }

        if (($maxLatency = $this->intValue($expectations, 'max_latency_ms')) !== null) {
            $latency = (int) ($run->latency_ms ?? 0);
            $ok = $latency <= $maxLatency;
            $checks[] = ['type' => 'latency', 'passed' => $ok, 'detail' => "Run latency {$latency}ms against a ceiling of {$maxLatency}ms."];
        }

        $passed = $completed && ! $this->hasFailure($checks);

        return [
            'passed' => $passed,
            'checks' => $checks,
            'citations' => $citations,
            'failure_reason' => $this->failureReason($completed, $checks, $run),
            'output' => $output,
        ];
    }

    /**
     * Extract the citations surfaced by any knowledge-retrieval tool in the run.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCitations(AgentRun $run): array
    {
        $citations = [];

        /** @var ToolCall $call */
        foreach ($run->toolCalls as $call) {
            if ($call->execution_mode !== ExecMode::Knowledge || ! is_array($call->result)) {
                continue;
            }

            foreach ((array) ($call->result['citations'] ?? []) as $citation) {
                if (is_array($citation)) {
                    $citations[] = $citation;
                }
            }
        }

        return $citations;
    }

    /**
     * Whether any check failed.
     *
     * @param  array<int, array{type: string, passed: bool, detail: string}>  $checks
     */
    private function hasFailure(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['passed'] === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the failure reason: the run's own reason when incomplete, otherwise
     * the first failing check's type.
     *
     * @param  array<int, array{type: string, passed: bool, detail: string}>  $checks
     */
    private function failureReason(bool $completed, array $checks, AgentRun $run): ?string
    {
        if (! $completed) {
            return $run->failure_reason ?? 'run_not_completed';
        }

        foreach ($checks as $check) {
            if ($check['passed'] === false) {
                return $check['type'].'_failed';
            }
        }

        return null;
    }

    /**
     * Read a list of non-empty strings from the expectations.
     *
     * @param  array<string, mixed>  $expectations
     * @return array<int, string>
     */
    private function stringList(array $expectations, string $key): array
    {
        $values = [];

        foreach ((array) ($expectations[$key] ?? []) as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return $values;
    }

    /**
     * Read a non-empty string value from the expectations (null when absent).
     *
     * @param  array<string, mixed>  $expectations
     */
    private function stringValue(array $expectations, string $key): ?string
    {
        $value = $expectations[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Read a float value from the expectations (null when absent).
     *
     * @param  array<string, mixed>  $expectations
     */
    private function floatValue(array $expectations, string $key): ?float
    {
        $value = $expectations[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Read an integer value from the expectations (null when absent).
     *
     * @param  array<string, mixed>  $expectations
     */
    private function intValue(array $expectations, string $key): ?int
    {
        $value = $expectations[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
