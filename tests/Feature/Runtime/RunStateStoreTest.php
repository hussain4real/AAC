<?php

use App\Enums\Environment;
use App\Enums\RunStatus;
use App\Support\Runtime\RunStateStore;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'maacc.runtime.state_store' => 'array',
        'maacc.runtime.state_ttl_seconds' => 300,
    ]);
});

test('runtime state is tenant scoped encrypted and never stored on the run', function () {
    [, $teamA] = ownerAndTeam();
    [, $teamB] = ownerAndTeam();
    $runA = maaccRun(maaccAgent($teamA), [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'state' => null,
        'expires_at' => now()->addMinutes(2),
    ]);
    $runB = maaccRun(maaccAgent($teamB), [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'state' => null,
        'expires_at' => now()->addMinutes(2),
    ]);
    $store = app(RunStateStore::class);
    $sentinel = 'tenant-a-secret-sentinel';

    $store->initialize($runA, ['messages' => [['content' => $sentinel]], 'steps' => 0]);

    $ciphertext = Cache::store('array')->get($store->key($runA));

    expect($runA->fresh()->state)->toBeNull()
        ->and($ciphertext)->toBeString()
        ->and($ciphertext)->not->toContain($sentinel)
        ->and($store->get($runA))->toBe(['messages' => [['content' => $sentinel]], 'steps' => 0])
        ->and($store->key($runA))->not->toBe($store->key($runB))
        ->and($store->get($runB))->toBe([]);
});

test('legacy database state is migrated once into the encrypted transient store', function () {
    [, $team] = ownerAndTeam();
    $run = maaccRun(maaccAgent($team), [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'state' => ['messages' => [['content' => 'legacy-secret']], 'steps' => 1],
        'expires_at' => now()->addMinutes(2),
    ]);
    $store = app(RunStateStore::class);

    expect($store->get($run))->toBe(['messages' => [['content' => 'legacy-secret']], 'steps' => 1])
        ->and($run->fresh()->state)->toBeNull()
        ->and((string) Cache::store('array')->get($store->key($run)))->not->toContain('legacy-secret');
});

test('forget removes both transient and legacy runtime state', function () {
    [, $team] = ownerAndTeam();
    $run = maaccRun(maaccAgent($team), [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'state' => ['messages' => []],
        'expires_at' => now()->addMinutes(2),
    ]);
    $store = app(RunStateStore::class);
    $store->put($run, ['messages' => [['content' => 'ephemeral']]]);

    $store->forget($run);

    expect(Cache::store('array')->has($store->key($run)))->toBeFalse()
        ->and($run->fresh()->state)->toBeNull();
});

test('tool arguments handle missing partial and non-array transient state', function () {
    [, $team] = ownerAndTeam();
    $run = maaccRun(maaccAgent($team), [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'state' => null,
        'expires_at' => null,
    ]);
    $store = app(RunStateStore::class);

    expect($store->toolArguments($run, 'missing'))->toBeNull();
    $store->forgetToolArguments($run, 'missing');

    $store->putToolArguments($run, 'call-a', ['value' => 'a']);
    $store->putToolArguments($run, 'call-b', ['value' => 'b']);
    $store->forgetToolArguments($run, 'call-a');

    expect($store->toolArguments($run, 'call-a'))->toBeNull()
        ->and($store->toolArguments($run, 'call-b'))->toBe(['value' => 'b']);

    $store->put($run, ['tool_arguments' => ['invalid' => 'not-an-array']]);

    expect($store->toolArguments($run, 'invalid'))->toBeNull();

    $run->forceFill(['state' => ['legacy' => 'restored']])->saveQuietly();
    $store->forget($run);

    expect(Cache::store('array')->has($store->key($run)))->toBeFalse()
        ->and($run->fresh()->state)->toBeNull();
});
