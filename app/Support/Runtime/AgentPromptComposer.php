<?php

namespace App\Support\Runtime;

use App\Models\Agent;
use App\Models\ToolContract;
use Illuminate\Support\Collection;

/**
 * Composes the effective system prompt MAAC sends to the model for a run.
 *
 * The user-authored {@see Agent::$system_prompt} captures the agent's intent and
 * is the only part a user edits. MAAC appends an auto-generated brief describing
 * the tools the agent has been given — derived entirely from the tool config —
 * so the model knows what each tool is for and where it runs without the user
 * having to document them by hand. The exact argument schemas are delivered
 * separately through the router's tool channel and are deliberately not repeated
 * here, so the brief stays a readable "what/why" rather than a schema dump.
 */
final class AgentPromptComposer
{
    /**
     * Build the effective system prompt: the user prompt followed by the
     * MAAC-generated tool brief (omitted when the agent has no tools).
     */
    public function compose(Agent $agent): string
    {
        $base = trim($agent->system_prompt);
        $brief = $this->toolBrief($agent);

        if ($brief === '') {
            return $base;
        }

        return $base === '' ? $brief : $base."\n\n".$brief;
    }

    /**
     * Build the "Tools available to you" section from the agent's assigned tool
     * contracts. Returns an empty string when the agent has none.
     */
    public function toolBrief(Agent $agent): string
    {
        /** @var Collection<int, ToolContract> $tools */
        $tools = $agent->tools;

        if ($tools->isEmpty()) {
            return '';
        }

        $entries = $tools
            ->map(fn (ToolContract $tool): string => $this->describe($tool))
            ->all();

        return implode("\n", [
            '## Tools available to you',
            'MAAC has connected the tools below to you. Call a tool by the name in backticks when it helps you answer the request; otherwise reply directly. Ground your answers in the results the tools return.',
            '',
            ...$entries,
        ]);
    }

    /**
     * Describe one tool: its call name, purpose, where it runs, and whether it
     * needs approval. The argument schema is supplied via the tool channel.
     */
    private function describe(ToolContract $tool): string
    {
        $purpose = trim((string) $tool->description);

        if ($purpose === '') {
            $purpose = 'No description provided.';
        }

        $approval = $tool->requires_approval ? ' (requires human approval)' : '';

        return sprintf(
            '- `%s` (%s) — %s [%s]%s',
            $tool->slug,
            $tool->name,
            $purpose,
            $tool->execution_mode->label(),
            $approval,
        );
    }
}
