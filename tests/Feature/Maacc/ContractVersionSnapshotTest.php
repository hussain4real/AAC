<?php

use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\UpdateToolContract;
use App\Enums\ExecMode;
use App\Models\ToolContract;
use App\Support\Sdk\ContractVersionRecorder;

test('creating a tool contract snapshots its initial version', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    $versions = $contract->versions()->orderBy('sequence')->get();

    expect($versions)->toHaveCount(1)
        ->and($versions[0]->sequence)->toBe(1)
        ->and($versions[0]->version)->toBe('1.0.0')
        ->and($versions[0]->execution_mode)->toBe(ExecMode::Client)
        ->and($versions[0]->schema_fingerprint)->toBe($contract->schemaFingerprint())
        ->and($versions[0]->input_schema)->toBe(['query' => 'string'])
        ->and($versions[0]->changed_by)->toBe($owner->id)
        ->and($versions[0]->actor_label)->toBe($owner->name)
        ->and($versions[0]->config)->toMatchArray(['timeout_seconds' => 15, 'requires_approval' => false]);
});

test('a material edit mints a new version and auto-bumps the patch', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['input_schema' => ['query' => 'string', 'limit' => 'number']]);

    $contract->refresh();

    expect($contract->version)->toBe('1.0.1')
        ->and($contract->versions()->count())->toBe(2)
        ->and($contract->versions()->orderByDesc('sequence')->first()->version)->toBe('1.0.1');
});

test('a non-schema functional edit (timeout) also mints a new version', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['timeout_seconds' => 30]);

    $contract->refresh();

    expect($contract->version)->toBe('1.0.1')
        ->and($contract->versions()->count())->toBe(2);
});

test('a cosmetic edit does not mint a new version', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['name' => 'Renamed', 'description' => 'New copy']);

    $contract->refresh();

    expect($contract->version)->toBe('1.0.0')
        ->and($contract->name)->toBe('Renamed')
        ->and($contract->description)->toBe('New copy')
        ->and($contract->versions()->count())->toBe(1);
});

test('a manually supplied higher version wins over the auto-bump', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, [
        'version' => '2.0.0',
        'input_schema' => ['query' => 'string', 'limit' => 'number'],
    ]);

    $contract->refresh();

    expect($contract->version)->toBe('2.0.0')
        ->and($contract->versions()->orderByDesc('sequence')->first()->version)->toBe('2.0.0');
});

test('a manually supplied non-higher version is overridden by the auto-bump', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.5.0']));

    app(UpdateToolContract::class)->handle($contract, [
        'version' => '1.1.0',
        'input_schema' => ['query' => 'string', 'limit' => 'number'],
    ]);

    expect($contract->fresh()->version)->toBe('1.5.1');
});

test('bumping the version alone (no schema change) mints a new version', function () {
    [, $team] = ownerAndTeam();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData(['version' => '1.0.0']));

    app(UpdateToolContract::class)->handle($contract, ['version' => '3.0.0']);

    $contract->refresh();

    expect($contract->version)->toBe('3.0.0')
        ->and($contract->versions()->count())->toBe(2);
});

test('the patch bump handles partial and non-numeric versions', function () {
    [, $team] = ownerAndTeam();
    $recorder = app(ContractVersionRecorder::class);

    $cases = [
        '1.2.3' => '1.2.4',
        '1.2' => '1.2.1',
        '7' => '7.0.1',
        'beta' => 'beta.1',
    ];

    foreach ($cases as $from => $expected) {
        // No prior snapshot → the latest version falls back to the contract's own;
        // a material schema change with no higher manual version forces a patch bump.
        $contract = ToolContract::factory()->for($team)->create([
            'execution_mode' => ExecMode::Client,
            'version' => $from,
        ]);

        $recorder->applyUpdate($contract, ['input_schema' => ['x' => 'string', 'y' => 'number']]);

        expect($contract->fresh()->version)->toBe($expected);
    }
});
