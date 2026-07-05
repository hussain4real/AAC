<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * MAACC console (Phase 1) smoke coverage: every screen in the sidebar
 * resolves for an authenticated, team-scoped user and renders the
 * expected Inertia page component.
 *
 * @return array<int, array{0: string, 1: array<string, string>, 2: string}>
 */
function maaccScreens(): array
{
    return [
        ['dashboard', [], 'dashboard'],
        ['applications', [], 'maacc/applications/index'],
        ['applications.show', ['application' => 'MOP'], 'maacc/applications/show'],
        ['projects', [], 'maacc/projects/index'],
        ['agents', [], 'maacc/agents/index'],
        ['agents.create', [], 'maacc/agents/create'],
        ['agents.show', ['agent' => 'ag_ops_summary'], 'maacc/agents/show'],
        ['tools', [], 'maacc/tools/index'],
        ['tools.show', ['tool' => 'getOperationalRecords'], 'maacc/tools/show'],
        ['sdk', [], 'maacc/sdk'],
        ['sdk.docs', [], 'maacc/sdk-docs'],
        ['playground', [], 'maacc/playground'],
        ['runs', [], 'maacc/runs/index'],
        ['runs.show', ['run' => 'run_8fa31c'], 'maacc/runs/show'],
        ['llm-providers', [], 'maacc/llm-providers'],
        ['governance', [], 'maacc/governance'],
        ['platform-settings', [], 'maacc/settings'],
    ];
}

test('authenticated users can reach every MAACC console screen', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    foreach (maaccScreens() as [$name, $params, $component]) {
        $this
            ->actingAs($user)
            ->get(route($name, array_merge(['current_team' => $team->slug], $params)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('guests are redirected from MAACC console routes to login', function () {
    $this
        ->get(route('applications', ['current_team' => 'any-team']))
        ->assertRedirect(route('login'));
});

test('console detail routes forward the record identifier as a prop', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this
        ->actingAs($user)
        ->get(route('applications.show', ['current_team' => $team->slug, 'application' => 'MOP']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('maacc/applications/show')
            ->where('id', 'MOP'),
        );

    $this
        ->actingAs($user)
        ->get(route('runs.show', ['current_team' => $team->slug, 'run' => 'run_8fa31c']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('maacc/runs/show')
            ->where('id', 'run_8fa31c'),
        );
});
