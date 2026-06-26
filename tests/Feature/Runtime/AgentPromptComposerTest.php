<?php

use App\Enums\ExecMode;
use App\Models\Agent;
use App\Models\ToolContract;
use App\Support\Runtime\AgentPromptComposer;
use Illuminate\Support\Collection;

/**
 * Build an unsaved agent with the given system prompt and a loaded `tools`
 * relation, so the composer can be exercised without touching the database.
 *
 * @param  Collection<int, ToolContract>|array<int, ToolContract>  $tools
 */
function agentWithTools(string $systemPrompt, Collection|array $tools = []): Agent
{
    $agent = Agent::factory()->make(['system_prompt' => $systemPrompt]);
    $agent->setRelation('tools', collect($tools));

    return $agent;
}

test('the composer returns the user prompt unchanged when the agent has no tools', function () {
    $prompt = (new AgentPromptComposer)->compose(agentWithTools('You summarize operations.'));

    expect($prompt)->toBe('You summarize operations.')
        ->and($prompt)->not->toContain('Tools available to you');
});

test('the composer appends a tool brief describing each tool below the user prompt', function () {
    $tool = ToolContract::factory()->make([
        'slug' => 'get_records',
        'name' => 'Get Records',
        'description' => 'Fetches operational voyage records.',
        'execution_mode' => ExecMode::Client,
        'requires_approval' => false,
    ]);

    $prompt = (new AgentPromptComposer)->compose(agentWithTools('You summarize operations.', [$tool]));

    expect($prompt)->toStartWith("You summarize operations.\n\n## Tools available to you")
        ->and($prompt)->toContain('`get_records` (Get Records)')
        ->and($prompt)->toContain('Fetches operational voyage records.')
        ->and($prompt)->toContain('['.ExecMode::Client->label().']')
        ->and($prompt)->not->toContain('requires human approval');
});

test('the composer flags approval-gated tools and falls back for missing descriptions', function () {
    $tool = ToolContract::factory()->make([
        'slug' => 'wire_transfer',
        'name' => 'Wire Transfer',
        'description' => '',
        'execution_mode' => ExecMode::Hosted,
        'requires_approval' => true,
    ]);

    // Empty base prompt: the composed prompt should be the brief on its own.
    $prompt = (new AgentPromptComposer)->compose(agentWithTools('', [$tool]));

    expect($prompt)->toStartWith('## Tools available to you')
        ->and($prompt)->toContain('No description provided.')
        ->and($prompt)->toContain('(requires human approval)');
});
