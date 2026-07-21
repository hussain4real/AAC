<?php

use App\Enums\MaaccRole;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Support\Governance\AuditLedger;
use App\Support\Governance\AuditSigner;
use Illuminate\Support\Facades\Storage;

test('a platform admin can export the audit log as JSON with a signed manifest', function () {
    Storage::fake('audit_archive');
    [$owner, $team] = ownerAndTeam();
    foreach (range(1, 3) as $index) {
        app(AuditLedger::class)->record(['team_id' => $team->id, 'action' => "test.event_{$index}"]);
    }

    $response = $this->actingAs($owner)
        ->get(route('audit-export', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Maacc-Audit-Count', '3');

    $payload = $response->json();

    expect($payload['manifest']['count'])->toBe(3)
        ->and($payload['manifest']['team'])->toBe($team->slug)
        ->and($payload['manifest']['rows_digest'])->toBe(hash('sha256', (string) json_encode($payload['events'])))
        ->and($payload['manifest']['chain_integrity']['valid'])->toBeTrue()
        ->and(app(AuditSigner::class)->verifyExport(
            collect($payload['manifest'])->except('signature')->all(),
            $payload['manifest']['signature'],
        ))->toBeTrue()
        ->and($payload['manifest']['truncated'])->toBeFalse()
        ->and($response->headers->get('X-Maacc-Audit-Signature'))->toBe($payload['manifest']['signature'])
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
