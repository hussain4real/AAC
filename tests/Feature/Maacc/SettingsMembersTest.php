<?php

use App\Enums\TeamRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the settings page surfaces the real team members and roles', function () {
    $owner = User::factory()->create(['name' => 'Aaa Owner']);
    $team = $owner->currentTeam;

    $member = User::factory()->create(['name' => 'Bbb Member', 'email' => 'bbb@example.test']);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->get(route('platform-settings', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/settings')
            ->missing('members')
            ->loadDeferredProps('members', fn (AssertableInertia $deferred) => $deferred
                ->has('members.items', 2)
                ->where('members.items.0.name', 'Aaa Owner')
                ->where('members.items.0.role', 'Owner')
                ->where('members.items.1.name', 'Bbb Member')
                ->where('members.items.1.email', 'bbb@example.test')
                ->where('members.items.1.role', 'Member')
                ->has('members.pagination')
            )
        );
});

test('the settings member directory is bounded for a large tenant', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $members = User::factory()->count(40)->create();
    $team->members()->attach($members, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->get(route('platform-settings', ['current_team' => $team->slug, 'per_page' => 25]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps('members', fn (AssertableInertia $deferred) => $deferred
                ->has('members.items', 25)
                ->where('members.pagination.hasMore', true)
                ->whereNot('members.pagination.nextCursor', null)));
});
