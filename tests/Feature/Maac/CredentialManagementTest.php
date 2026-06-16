<?php

use App\Enums\CredentialStatus;
use App\Models\Application;
use App\Models\Credential;
use Illuminate\Support\Facades\Hash;

test('generating a credential stores a hashed secret and never the plaintext', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $response = $this->actingAs($owner)
        ->post(route('applications.credentials.store', ['current_team' => $team->slug, 'application' => $application->slug]), [
            'environment' => 'production',
        ]);

    $response->assertRedirect();
    expect($response->getSession()->get('inertia.flash_data'))->toHaveKey('credentialSecret');

    $credential = $application->credentials()->first();

    expect($credential)->not->toBeNull()
        ->and($credential->secret_hash)->not->toBeEmpty()
        ->and($credential->secret_hash)->not->toStartWith('maac_sk_')
        ->and($credential->last_four)->toHaveLength(4)
        ->and($credential->status)->toBe(CredentialStatus::Active);
});

test('the one-time secret is a valid hash of the displayed plaintext', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $response = $this->actingAs($owner)
        ->post(route('applications.credentials.store', ['current_team' => $team->slug, 'application' => $application->slug]), [
            'environment' => 'staging',
        ]);

    $plain = $response->getSession()->get('inertia.flash_data')['credentialSecret']['secret'];
    $credential = $application->credentials()->first();

    expect(Hash::check($plain, $credential->secret_hash))->toBeTrue();
});

test('a non-admin cannot generate a credential', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $application = Application::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('applications.credentials.store', ['current_team' => $team->slug, 'application' => $application->slug]), [
            'environment' => 'production',
        ])
        ->assertForbidden();
});

test('rotating a credential issues a new secret and keeps it active', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();
    $originalHash = $credential->secret_hash;

    $this->actingAs($owner)
        ->post(route('credentials.rotate', ['current_team' => $team->slug, 'credential' => $credential->id]))
        ->assertRedirect();

    $credential->refresh();

    expect($credential->secret_hash)->not->toBe($originalHash)
        ->and($credential->rotated_at)->not->toBeNull()
        ->and($credential->status)->toBe(CredentialStatus::Active);
});

test('revoking a credential blocks further use', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();

    $this->actingAs($owner)
        ->post(route('credentials.revoke', ['current_team' => $team->slug, 'credential' => $credential->id]))
        ->assertRedirect();

    $credential->refresh();

    expect($credential->status)->toBe(CredentialStatus::Revoked)
        ->and($credential->revoked_at)->not->toBeNull()
        ->and($credential->isUsable())->toBeFalse();
});
