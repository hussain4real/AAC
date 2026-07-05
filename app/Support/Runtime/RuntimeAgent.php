<?php

namespace App\Support\Runtime;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;

/**
 * The single-turn `laravel/ai` agent the runtime drives one step at a time.
 *
 * Capped at one step (`#[MaxSteps(1)]`) so the SDK returns any native tool call
 * to MAACC un-executed: MAACC owns the orchestration loop, so it routes each call
 * by execution mode and can pause for client-side tools, which the SDK's
 * auto-executing loop cannot do.
 */
#[MaxSteps(1)]
class RuntimeAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  array<int, RuntimeTool|ProviderTool>  $tools
     */
    public function __construct(
        private readonly string $systemInstructions,
        private readonly array $tools,
    ) {}

    /**
     * The system instructions the model should follow.
     */
    public function instructions(): string
    {
        return $this->systemInstructions;
    }

    /**
     * The conversation history. MAACC renders the full history into the prompt
     * itself, so the agent carries none of its own.
     *
     * @return array<int, never>
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * The native tools offered to the model for this turn.
     *
     * @return array<int, RuntimeTool|ProviderTool>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
