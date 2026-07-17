<?php

use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'session.driver' => 'database',
        'session.connection' => null,
        'session.table' => 'sessions',
    ]);
    $this->seed(PlatformRbacSeeder::class);
    $this->actor = User::factory()->create();
});

test('the audit exports privileged identity and active session findings without secrets or mutation', function () {
    $administrator = User::factory()->create([
        'email' => 'administrator@example.com',
    ]);
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::PlatformAdmin,
        $this->actor,
        'Privileged access audit fixture',
    );

    $connection = SsoConnection::factory()->disabled()->create();
    $identity = SsoIdentity::factory()->for($connection, 'connection')->for($administrator)->create([
        'email' => 'different@example.com',
        'subject' => 'sensitive-external-subject',
        'raw_claims' => ['access_token' => 'must-never-be-exported'],
    ]);
    DB::table('sessions')->insert([
        'id' => 'sensitive-session-id',
        'user_id' => $administrator->id,
        'ip_address' => '192.0.2.10',
        'user_agent' => 'Audit test browser',
        'payload' => 'must-never-be-exported-session-payload',
        'last_activity' => now()->getTimestamp(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $codes = collect($report['findings'])->pluck('code');

    expect($exitCode)->toBe(1)
        ->and($report['read_only'])->toBeTrue()
        ->and($report['privileged_user_count'])->toBe(1)
        ->and($report['privileged_identity_count'])->toBe(1)
        ->and($report['active_session_count'])->toBe(1)
        ->and($codes)->toContain(
            'privileged_sso_identity',
            'privileged_identity_email_mismatch',
            'privileged_identity_inactive_connection',
            'privileged_local_mfa_not_confirmed',
        )
        ->and($output)->not->toContain(
            'sensitive-external-subject',
            'sensitive-session-id',
            'must-never-be-exported',
            'must-never-be-exported-session-payload',
        )
        ->and(SsoIdentity::query()->find($identity->id))->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'sensitive-session-id')->exists())->toBeTrue();
});

test('a locally protected administrator with a governed grant produces no finding', function () {
    $administrator = User::factory()->create(['remember_token' => null]);
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::SuperAdmin,
        $this->actor,
        'Local recovery custodian',
    );
    DB::table('passkeys')->insert([
        'user_id' => $administrator->id,
        'name' => 'Security key',
        'credential_id' => 'credential-id',
        'credential' => json_encode(['id' => 'credential-id'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['finding_count'])->toBe(0)
        ->and($report['privileged_users'][0]['local_factors']['passkey_registered'])->toBeTrue();
});

test('the audit reports when the configured session driver cannot be inventoried', function () {
    config(['session.driver' => 'file']);
    $administrator = User::factory()->create();
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::PlatformAdmin,
        $this->actor,
        'Unsupported session inventory fixture',
    );
    DB::table('passkeys')->insert([
        'user_id' => $administrator->id,
        'name' => 'Security key',
        'credential_id' => 'unsupported-driver-credential',
        'credential' => json_encode(['id' => 'unsupported-driver-credential'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($report['session_coverage'])->toMatchArray([
            'driver' => 'file',
            'supported' => false,
            'available' => false,
            'table' => null,
        ])
        ->and(collect($report['findings'])->pluck('code'))
        ->toContain('privileged_session_inventory_unsupported');
});

test('the audit uses the configured database connection and session table', function () {
    Schema::connection('sqlite')->create('privileged_audit_sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });

    try {
        config([
            'session.driver' => 'database',
            'session.connection' => 'sqlite',
            'session.table' => 'privileged_audit_sessions',
        ]);
        $administrator = User::factory()->create(['remember_token' => null]);
        app(PlatformAccessManager::class)->grant(
            $administrator,
            PlatformRole::SuperAdmin,
            $this->actor,
            'Configured session inventory fixture',
        );
        DB::table('passkeys')->insert([
            'user_id' => $administrator->id,
            'name' => 'Security key',
            'credential_id' => 'configured-table-credential',
            'credential' => json_encode(['id' => 'configured-table-credential'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('sqlite')->table('privileged_audit_sessions')->insert([
            'id' => 'configured-sensitive-session-id',
            'user_id' => $administrator->id,
            'ip_address' => '192.0.2.20',
            'user_agent' => 'Configured audit browser',
            'payload' => 'configured-sensitive-session-payload',
            'last_activity' => now()->getTimestamp(),
        ]);

        $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($report['active_session_count'])->toBe(1)
            ->and($report['session_coverage'])->toMatchArray([
                'driver' => 'database',
                'supported' => true,
                'available' => true,
                'connection' => 'sqlite',
                'table' => 'privileged_audit_sessions',
                'reason' => null,
            ])
            ->and($output)->not->toContain(
                'configured-sensitive-session-id',
                'configured-sensitive-session-payload',
            );
    } finally {
        Schema::connection('sqlite')->dropIfExists('privileged_audit_sessions');
    }
});

test('the audit reports persistent privileged remember tokens without exporting them', function () {
    $administrator = User::factory()->create();
    $administrator->forceFill(['remember_token' => 'sensitive-persistent-token'])->save();
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::PlatformAdmin,
        $this->actor,
        'Remember-token fixture',
    );
    DB::table('passkeys')->insert([
        'user_id' => $administrator->id,
        'name' => 'Security key',
        'credential_id' => 'remember-token-credential',
        'credential' => json_encode(['id' => 'remember-token-credential'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and(collect($report['findings'])->pluck('code'))
        ->toContain('privileged_remember_token_present')
        ->and($output)->not->toContain('sensitive-persistent-token');
});

test('the audit reports reverse ledger drift and duplicate active grants', function () {
    $administrator = User::factory()->create();
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::SuperAdmin,
        $this->actor,
        'Reverse drift fixture',
    );
    PlatformAccessGrant::factory()->create([
        'user_id' => $administrator->id,
        'role' => PlatformRole::SuperAdmin,
        'granted_by' => $this->actor->id,
    ]);
    $administrator->removeRole(PlatformRole::SuperAdmin->value);
    DB::table('passkeys')->insert([
        'user_id' => $administrator->id,
        'name' => 'Security key',
        'credential_id' => 'reverse-drift-credential',
        'credential' => json_encode(['id' => 'reverse-drift-credential'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $codes = collect($report['findings'])->pluck('code');

    expect($exitCode)->toBe(1)
        ->and($report['privileged_user_count'])->toBe(1)
        ->and($codes)->toContain(
            'active_grant_without_platform_role',
            'duplicate_active_platform_grants',
        );
});

test('the human-readable audit renders its finding table', function () {
    $administrator = User::factory()->create(['remember_token' => null]);
    app(PlatformAccessManager::class)->grant(
        $administrator,
        PlatformRole::PlatformAdmin,
        $this->actor,
        'Human-readable report fixture',
    );

    $exitCode = Artisan::call('maacc:audit-sso-access');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain(
            'Privileged SSO identity and session audit completed in read-only mode.',
            'Privileged users: 1',
            'privileged_local_mfa_not_confirmed',
        );
});

test('the audit reports an unavailable database inventory and an ungoverned role', function () {
    config(['session.table' => 'missing_privileged_sessions']);
    $administrator = User::factory()->create(['remember_token' => null]);
    $administrator->assignRole(PlatformRole::PlatformAdmin->value);
    DB::table('passkeys')->insert([
        'user_id' => $administrator->id,
        'name' => 'Security key',
        'credential_id' => 'unavailable-session-credential',
        'credential' => json_encode(['id' => 'unavailable-session-credential'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('maacc:audit-sso-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $codes = collect($report['findings'])->pluck('code');

    expect($exitCode)->toBe(1)
        ->and($report['session_coverage'])->toMatchArray([
            'supported' => true,
            'available' => false,
            'reason' => 'The configured database session inventory is unavailable.',
        ])
        ->and($codes)->toContain(
            'privileged_session_inventory_unavailable',
            'platform_role_without_active_grant',
        );
});
