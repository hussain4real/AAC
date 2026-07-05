<?php

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\MaaccConsoleData;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    [$this->owner, $this->team] = ownerAndTeam();
    $this->agent = maaccAgent($this->team);
    $this->application = $this->agent->project->application;
});

test('the webhooks console page renders', function () {
    $this->actingAs($this->owner)
        ->get(route('webhooks', ['current_team' => $this->team->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('maacc/webhooks'));
});

test('a platform admin registers a webhook endpoint and sees the one-time secret', function () {
    $response = $this->actingAs($this->owner)
        ->post(route('webhooks.store', ['current_team' => $this->team->slug]), [
            'application_id' => $this->application->id,
            'environment' => 'production',
            'url' => 'https://app.example.com/webhooks/maacc',
            'events' => [WebhookEventType::RunCompleted->value],
        ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $secret = $response->getSession()->get('inertia.flash_data')['webhookSecret'];
    expect($secret['secret'])->toStartWith('whsec_');

    $endpoint = WebhookEndpoint::first();
    expect($endpoint->application_id)->toBe($this->application->id)
        ->and($endpoint->events)->toBe([WebhookEventType::RunCompleted->value])
        ->and($endpoint->status)->toBe(WebhookEndpointStatus::Active)
        ->and($endpoint->creator->is($this->owner))->toBeTrue();
});

test('registering without events defaults to all events', function () {
    $this->actingAs($this->owner)
        ->post(route('webhooks.store', ['current_team' => $this->team->slug]), [
            'application_id' => $this->application->id,
            'environment' => 'production',
            'url' => 'https://app.example.com/webhooks/maacc',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(WebhookEndpoint::first()->events)->toBe(['*']);
});

test('registration without a URL fails validation', function () {
    $this->actingAs($this->owner)
        ->from(route('webhooks', ['current_team' => $this->team->slug]))
        ->post(route('webhooks.store', ['current_team' => $this->team->slug]), [
            'application_id' => $this->application->id,
            'environment' => 'production',
        ])
        ->assertSessionHasErrors('url');

    expect(WebhookEndpoint::count())->toBe(0);
});

test('an endpoint can be toggled, edited, rotated, and deleted', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->application)->create([
        'status' => WebhookEndpointStatus::Active,
    ]);
    $originalSecret = $endpoint->secret;

    // Disable.
    $this->actingAs($this->owner)
        ->put(route('webhooks.update', ['current_team' => $this->team->slug, 'webhookEndpoint' => $endpoint->id]), [
            'status' => 'disabled',
        ])
        ->assertRedirect();
    expect($endpoint->fresh()->status)->toBe(WebhookEndpointStatus::Disabled);

    // Rotate — re-displays a new secret.
    $rotate = $this->actingAs($this->owner)
        ->post(route('webhooks.rotate', ['current_team' => $this->team->slug, 'webhookEndpoint' => $endpoint->id]));
    $rotate->assertRedirect();
    expect($rotate->getSession()->get('inertia.flash_data')['webhookSecret']['secret'])->toStartWith('whsec_')
        ->and($endpoint->fresh()->secret)->not->toBe($originalSecret);

    // Delete.
    $this->actingAs($this->owner)
        ->delete(route('webhooks.destroy', ['current_team' => $this->team->slug, 'webhookEndpoint' => $endpoint->id]))
        ->assertRedirect();
    expect(WebhookEndpoint::find($endpoint->id))->toBeNull();
});

test('a failed delivery is replayed from the console', function () {
    Queue::fake();

    $endpoint = WebhookEndpoint::factory()->for($this->application)->create();
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->failed()->create();

    $this->actingAs($this->owner)
        ->post(route('webhook-deliveries.replay', ['current_team' => $this->team->slug, 'webhookDelivery' => $delivery->id]))
        ->assertRedirect();

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->fresh()->attempts)->toBe(0);

    Queue::assertPushed(DeliverWebhook::class);
});

test('a non-failed delivery cannot be replayed', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->application)->create();
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->delivered()->create();

    $this->actingAs($this->owner)
        ->from(route('webhooks', ['current_team' => $this->team->slug]))
        ->post(route('webhook-deliveries.replay', ['current_team' => $this->team->slug, 'webhookDelivery' => $delivery->id]))
        ->assertRedirect();

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Delivered);
});

test('a non-admin team member cannot manage webhooks', function () {
    $member = teamMember($this->team);

    $this->actingAs($member)
        ->post(route('webhooks.store', ['current_team' => $this->team->slug]), [
            'application_id' => $this->application->id,
            'environment' => 'production',
            'url' => 'https://app.example.com/webhooks/maacc',
        ])
        ->assertForbidden();
});

test('the console dataset includes webhook endpoints with their recent deliveries', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->application)->create();
    WebhookDelivery::factory()->for($endpoint, 'endpoint')->delivered()->create([
        'event' => WebhookEventType::RunCompleted,
    ]);

    $data = MaaccConsoleData::forTeam($this->team);

    expect($data['webhooks'])->toHaveCount(1)
        ->and($data['webhooks'][0]['url'])->toBe($endpoint->url)
        ->and($data['webhooks'][0]['deliveries'])->toHaveCount(1)
        ->and($data['webhooks'][0]['deliveries'][0]['eventLabel'])->toBe('Run completed');
});
