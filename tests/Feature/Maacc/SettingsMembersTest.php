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
            ->has('members', 2)
            ->where('members.0.name', 'Aaa Owner')
            ->where('members.0.role', 'Owner')
            ->where('members.1.name', 'Bbb Member')
            ->where('members.1.email', 'bbb@example.test')
            ->where('members.1.role', 'Member')
        );
});
