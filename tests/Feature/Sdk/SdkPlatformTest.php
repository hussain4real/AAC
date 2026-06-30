<?php

use App\Support\Sdk\SdkPlatform;

beforeEach(function () {
    $this->platform = new SdkPlatform;
});

test('it exposes the configured api and client versions', function () {
    config()->set('maac.sdk.api_version', '1.2.3');
    config()->set('maac.sdk.minimum_client_version', '1.1.0');
    config()->set('maac.sdk.current_client_version', '1.4.0');

    expect($this->platform->apiVersion())->toBe('1.2.3')
        ->and($this->platform->minimumClientVersion())->toBe('1.1.0')
        ->and($this->platform->currentClientVersion())->toBe('1.4.0');
});

test('it falls back to defaults when config values are missing or non-string', function () {
    config()->set('maac.sdk.api_version', null);
    config()->set('maac.sdk.minimum_client_version', ['not', 'a', 'string']);
    config()->set('maac.sdk.current_client_version', '');

    expect($this->platform->apiVersion())->toBe('0.0.1')
        ->and($this->platform->minimumClientVersion())->toBe('0.0.1')
        ->and($this->platform->currentClientVersion())->toBe('0.0.1');
});

test('it normalises the package registry and ignores malformed entries', function () {
    config()->set('maac.sdk.packages', [
        'php' => ['name' => 'maac/sdk', 'version' => '0.0.1', 'registry' => 'composer-vcs', 'status' => 'supported'],
        'broken' => 'not-an-array',
    ]);

    expect($this->platform->packages())->toBe([
        ['language' => 'php', 'name' => 'maac/sdk', 'version' => '0.0.1', 'registry' => 'composer-vcs', 'status' => 'supported'],
    ]);
});

test('packages is empty when the config is not an array', function () {
    config()->set('maac.sdk.packages', 'nope');

    expect($this->platform->packages())->toBe([]);
});

test('it normalises deprecations and ignores malformed entries', function () {
    config()->set('maac.sdk.deprecations', [
        ['id' => 'legacy-shape', 'removed_in' => '2.0.0'],
        'not-an-array',
    ]);

    expect($this->platform->deprecations())->toBe([
        ['id' => 'legacy-shape', 'removed_in' => '2.0.0'],
    ]);
});

test('deprecations is empty when the config is not an array', function () {
    config()->set('maac.sdk.deprecations', null);

    expect($this->platform->deprecations())->toBe([]);
});

test('the descriptor carries the full versioned contract', function () {
    config()->set('maac.sdk.api_version', '0.0.1');

    $descriptor = $this->platform->descriptor();

    expect($descriptor)->toHaveKeys([
        'api_version',
        'minimum_client_version',
        'current_client_version',
        'languages',
        'packages',
        'deprecations',
    ])->and(collect($descriptor['languages'])->pluck('value')->all())
        ->toBe(['typescript', 'php', 'python']);
});

test('a client within the supported window is compatible', function () {
    config()->set('maac.sdk.minimum_client_version', '0.0.1');
    config()->set('maac.sdk.current_client_version', '0.2.0');

    $result = $this->platform->compatibility('0.1.0', 'php');

    expect($result['status'])->toBe(SdkPlatform::STATUS_COMPATIBLE)
        ->and($result['compatible'])->toBeTrue()
        ->and($result['upgrade_required'])->toBeFalse()
        ->and($result['client_version'])->toBe('0.1.0')
        ->and($result['language'])->toBe('php');
});

test('a client older than the minimum requires an upgrade', function () {
    config()->set('maac.sdk.minimum_client_version', '0.1.0');
    config()->set('maac.sdk.current_client_version', '0.2.0');

    $result = $this->platform->compatibility('0.0.1');

    expect($result['status'])->toBe(SdkPlatform::STATUS_UPGRADE_REQUIRED)
        ->and($result['compatible'])->toBeFalse()
        ->and($result['upgrade_required'])->toBeTrue();
});

test('a client ahead of the current version is compatible but flagged', function () {
    config()->set('maac.sdk.minimum_client_version', '0.0.1');
    config()->set('maac.sdk.current_client_version', '0.2.0');

    $result = $this->platform->compatibility('2.0.0');

    expect($result['status'])->toBe(SdkPlatform::STATUS_AHEAD)
        ->and($result['compatible'])->toBeTrue()
        ->and($result['upgrade_required'])->toBeFalse();
});

test('an unreported client version is unknown but not blocked', function () {
    $result = $this->platform->compatibility('  ');

    expect($result['status'])->toBe(SdkPlatform::STATUS_UNKNOWN)
        ->and($result['compatible'])->toBeTrue()
        ->and($result['client_version'])->toBeNull()
        ->and($result['language'])->toBeNull();
});
