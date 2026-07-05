<?php

use App\Enums\MaaccRole;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Project;

test('a platform admin can export the audit log as JSON with a signed manifest', function () {
    [$owner, $team] = ownerAndTeam();
    AuditEvent::factory()->for($team)->count(3)->create();

    $response = $this->actingAs($owner)
        ->get(route('audit-export', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Maacc-Audit-Count', '3');

    $payload = $response->json();

    expect($payload['manifest']['count'])->toBe(3)
        ->and($payload['manifest']['team'])->toBe($team->slug)
        ->and($payload['manifest']['checksum'])->toBe(hash('sha256', (string) json_encode($payload['events'])))
        ->and($payload['manifest']['truncated'])->toBeFalse()
        ->and($response->headers->get('X-Maacc-Audit-Checksum'))->toBe($payload['manifest']['checksum'])
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('the audit export can be filtered by action prefix and downloaded as CSV', function () {
    [$owner, $team] = ownerAndTeam();
    AuditEvent::factory()->for($team)->create(['action' => 'incident.disable_model', 'metadata' => ['reason' => 'bad, output', 'severity' => 'high']]);
    AuditEvent::factory()->for($team)->create(['action' => 'incident.freeze_application']);
    AuditEvent::factory()->for($team)->create(['action' => 'agent.created']);

    $response = $this->actingAs($owner)
        ->get(route('audit-export', ['current_team' => $team->slug, 'action' => 'incident.', 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('X-Maacc-Audit-Count', '2');

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->content())->toContain('incident.disable_model')
        ->toContain('incident.freeze_application')
        ->not->toContain('agent.created');
});

test('the audit export validates the format', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get(route('audit-export', ['current_team' => $team->slug, 'format' => 'pdf']))
        ->assertSessionHasErrors('format');
});

test('a developer without audit access cannot export the audit log', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $developer = projectRoleUser($team, $project, MaaccRole::Developer);

    $this->actingAs($developer)
        ->get(route('audit-export', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('an auditor can export the audit log', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $auditor = projectRoleUser($team, $project, MaaccRole::Auditor);

    $this->actingAs($auditor)
        ->get(route('audit-export', ['current_team' => $team->slug]))
        ->assertOk();
});
