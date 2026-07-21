<?php

use App\Actions\Maacc\ApproveApprovalRequest;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\CredentialStatus;
use App\Enums\TeamRole;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Once;

test('generating a credential stores a hashed secret and never the plaintext', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $response = $this->actingAs($owner)
        ->post(route('applications.credentials.store', ['current_team' => $team->slug, 'application' => $application->slug]), [
            'environment' => 'production',
        ]);

    $response->assertRedirect();
    expect($response->getSession()->get('inertia.flash_data'))->toHaveKey('credentialSecret');
    $plainSecret = $response->getSession()->get('inertia.flash_data')['credentialSecret']['secret'];

    $credential = $application->credentials()->first();

    expect($credential)->not->toBeNull()
        ->and($credential->secret_hash)->not->toBeEmpty()
        ->and($credential->secret_hash)->not->toStartWith('maacc_sk_')
        ->and($credential->last_four)->toHaveLength(4)
        ->and($credential->status)->toBe(CredentialStatus::PendingApproval)
        ->and($credential->oauthClient->revoked)->toBeTrue();

    expect(ApprovalRequest::query()
        ->where('type', ApprovalType::CredentialChange)
        ->where('subject_id', $credential->id)
        ->where('status', ApprovalStatus::Pending)
        ->exists())->toBeTrue();

    $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $credential->client_id,
        'client_secret' => $plainSecret,
    ])->assertUnauthorized();

    $reviewer = User::factory()->create();
    $team->members()->attach($reviewer, ['role' => TeamRole::Admin->value]);
    $reviewer->switchTeam($team);
    $approval = ApprovalRequest::query()->where('subject_id', $credential->id)->firstOrFail();
    app(ApproveApprovalRequest::class)->handle($approval, $reviewer);

    expect($credential->fresh()->status)->toBe(CredentialStatus::Active)
        ->and($credential->fresh()->oauthClient->revoked)->toBeFalse();

    Once::flush();

    $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $credential->client_id,
        'client_secret' => $plainSecret,
    ])->assertOk();
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

test('production credential rotation is staged until a separate reviewer approves it', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = User::factory()->create();
    $team->members()->attach($reviewer, ['role' => TeamRole::Admin->value]);
    $reviewer->switchTeam($team);
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->withOauthClient()->create();
    $originalHash = $credential->secret_hash;

    $response = $this->actingAs($owner)
        ->post(route('credentials.rotate', ['current_team' => $team->slug, 'credential' => $credential->id]))
        ->assertRedirect();

    $credential->refresh();
    $plainSecret = $response->getSession()->get('inertia.flash_data')['credentialSecret']['secret'];
    $approval = ApprovalRequest::query()->where('subject_id', $credential->id)->pending()->firstOrFail();

    expect($credential->secret_hash)->toBe($originalHash)
        ->and($credential->rotated_at)->toBeNull()
        ->and($approval->type)->toBe(ApprovalType::CredentialChange)
        ->and($approval->getRawOriginal('encrypted_payload'))->not->toContain($plainSecret);

    app(ApproveApprovalRequest::class)->handle($approval, $reviewer);
    $credential->refresh();

    expect(Hash::check($plainSecret, $credential->secret_hash))->toBeTrue()
        ->and($credential->rotated_at)->not->toBeNull()
        ->and($credential->status)->toBe(CredentialStatus::Active);
});

test('non-production credential rotation remains immediate', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->withOauthClient()->create([
        'environment' => 'staging',
    ]);
    $originalHash = $credential->secret_hash;

    $this->actingAs($owner)
        ->post(route('credentials.rotate', ['current_team' => $team->slug, 'credential' => $credential->id]))
        ->assertRedirect();

    expect($credential->fresh()->secret_hash)->not->toBe($originalHash)
        ->and(ApprovalRequest::query()->where('subject_id', $credential->id)->exists())->toBeFalse();
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
