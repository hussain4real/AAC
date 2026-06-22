<?php

use App\Support\Sdk\ContractFixtures;
use Illuminate\Support\Facades\File;

/**
 * Guards the shared SDK contract fixture suite (packages/sdk-fixtures). Because
 * the fixtures are generated from MAAC's own logic, a drift here means a
 * server-side rule or response-shape change that has not been propagated to the
 * SDK languages — exactly the silent break Phase 6C is meant to catch.
 */
test('the committed contract fixtures match what MAAC currently produces', function () {
    $path = base_path('packages/sdk-fixtures/contract.json');

    expect(File::exists($path))->toBeTrue('Run `php artisan maac:sdk-fixtures` to generate the fixtures.')
        ->and(File::get($path))->toBe(
            ContractFixtures::toJson(),
            'SDK contract fixtures are out of date — run `php artisan maac:sdk-fixtures` and commit.',
        );
});

test('the fixture suite covers every contract dimension', function () {
    $fixtures = ContractFixtures::build();

    expect($fixtures)->toHaveKeys([
        'api_version',
        'schema_validation',
        'fingerprint',
        'compatibility',
        'version_negotiation',
        'errors',
    ])->and($fixtures['schema_validation'])->not->toBeEmpty()
        ->and($fixtures['compatibility'])->not->toBeEmpty();
});

test('the command verifies up-to-date fixtures', function () {
    $this->artisan('maac:sdk-fixtures', ['--check' => true])->assertSuccessful();
});

test('the command writes the fixtures and they then verify clean', function () {
    $this->artisan('maac:sdk-fixtures')->assertSuccessful();
    $this->artisan('maac:sdk-fixtures', ['--check' => true])->assertSuccessful();
});

test('the check option fails when the committed fixtures drift', function () {
    $path = base_path('packages/sdk-fixtures/contract.json');
    $original = File::get($path);
    File::put($path, '{"drifted":true}');

    try {
        $this->artisan('maac:sdk-fixtures', ['--check' => true])->assertFailed();
    } finally {
        File::put($path, $original);
    }
});
