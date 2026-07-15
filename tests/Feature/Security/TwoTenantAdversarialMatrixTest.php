<?php

use App\Enums\Environment;
use App\Models\Agent;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    [$this->tenantAOwner, $this->tenantA] = ownerAndTeam();
    [, $this->tenantB] = ownerAndTeam();

    $this->applicationA = Application::factory()->for($this->tenantA)->create([
        'environment' => Environment::Production,
    ]);
    $this->applicationB = Application::factory()->for($this->tenantB)->create([
        'environment' => Environment::Production,
    ]);

    $this->projectA = Project::factory()->for($this->applicationA)->create([
        'environment' => Environment::Production,
    ]);
    $this->projectB = Project::factory()->for($this->applicationB)->create([
        'environment' => Environment::Production,
    ]);

    $this->providerA = LlmProvider::factory()->for($this->tenantA)->create();
    $this->providerB = LlmProvider::factory()->for($this->tenantB)->create();
    $this->projectA->llmProviders()->attach($this->providerA);
    $this->projectB->llmProviders()->attach($this->providerB);

    $this->agentB = Agent::factory()
        ->for($this->projectB)
        ->for($this->providerB, 'llmProvider')
        ->published()
        ->create(['agent_slug' => 'tenant-b-agent']);

    $this->toolB = ToolContract::factory()
        ->for($this->tenantB)
        ->for($this->applicationB)
        ->create(['slug' => 'tenant-b-tool']);

    $this->credentialA = Credential::factory()
        ->for($this->applicationA)
        ->withOauthClient()
        ->create(['environment' => Environment::Production]);
});

describe('two-tenant adversarial matrix', function () {
    test('create rejects a foreign parent', function () {
        $this->actingAs($this->tenantAOwner)
            ->post(route('projects.store', ['current_team' => $this->tenantA->slug]), [
                'application_id' => $this->applicationB->id,
                'name' => 'Injected Tenant B Project',
                'environment' => Environment::Production->value,
            ])
            ->assertSessionHasErrors('application_id');

        expect(Project::query()->where('name', 'Injected Tenant B Project')->exists())->toBeFalse();
    });

    test('update rejects a foreign object', function () {
        $this->actingAs($this->tenantAOwner)
            ->put(route('agents.update', [
                'current_team' => $this->tenantA->slug,
                'agent' => $this->agentB->slug,
            ]), ['name' => 'Cross-tenant update'])
            ->assertNotFound();

        expect($this->agentB->fresh()->name)->not->toBe('Cross-tenant update');
    });

    test('import remains unavailable until a tenant-scoped import contract is implemented', function () {
        $importRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $name = Str::lower((string) $route->getName());
                $uri = Str::lower($route->uri());

                return Str::contains($name, 'import') || Str::contains($uri, 'import');
            });

        expect($importRoutes)->toBeEmpty();
    });

    test('manifest excludes foreign agents and tools', function () {
        Passport::actingAsClient($this->credentialA->oauthClient, [], 'api');

        $response = $this->getJson('/api/v1/manifest')->assertOk();

        expect(collect($response->json('agents'))->pluck('slug'))->not->toContain($this->agentB->agent_slug)
            ->and(collect($response->json('tools'))->pluck('name'))->not->toContain('tenant-b-tool');
    });

    test('publish rejects a foreign agent', function () {
        $this->agentB->forceFill(['status' => 'draft'])->save();

        $this->actingAs($this->tenantAOwner)
            ->post(route('agents.publish', [
                'current_team' => $this->tenantA->slug,
                'agent' => $this->agentB->slug,
            ]))
            ->assertNotFound();

        expect($this->agentB->fresh()->status->value)->toBe('draft');
    });

    test('execute returns the same not-found envelope for a foreign agent', function () {
        Passport::actingAsClient($this->credentialA->oauthClient, [], 'api');

        $this->postJson('/api/v1/agents/'.$this->agentB->agent_slug.'/runs', [
            'input' => 'Attempt cross-tenant execution',
        ])
            ->assertNotFound()
            ->assertJsonPath('error', 'agent_not_found');
    });

    test('export contains only the selected tenants audit records', function () {
        AuditEvent::factory()->for($this->tenantA)->create(['action' => 'tenant_a.only']);
        AuditEvent::factory()->for($this->tenantB)->create(['action' => 'tenant_b.secret']);

        $response = $this->actingAs($this->tenantAOwner)
            ->get(route('audit-export', ['current_team' => $this->tenantA->slug]))
            ->assertOk();

        expect(collect($response->json('events'))->pluck('action'))
            ->toContain('tenant_a.only')
            ->not->toContain('tenant_b.secret');
    });

    test('direct object access cannot expose a foreign application', function () {
        $this->actingAs($this->tenantAOwner)
            ->get(route('applications.show', [
                'current_team' => $this->tenantA->slug,
                'application' => $this->applicationB->slug,
            ]))
            ->assertNotFound();
    });
});
