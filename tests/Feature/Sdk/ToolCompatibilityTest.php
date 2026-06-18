<?php

use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\ToolContract;
use App\Support\Sdk\ToolCompatibility;

test('the fingerprint is stable regardless of field order or whitespace', function () {
    $a = ToolCompatibility::fingerprint(
        ['b' => 'string', 'a' => 'number?'],
        ['y' => 'array'],
    );
    $b = ToolCompatibility::fingerprint(
        ['a' => 'number?', 'b' => ' string '],
        ['y' => 'array'],
    );

    expect($a)->toBe($b);
});

test('the fingerprint changes when the schema shape changes', function () {
    $a = ToolCompatibility::fingerprint(['a' => 'string'], ['y' => 'array']);
    $b = ToolCompatibility::fingerprint(['a' => 'number'], ['y' => 'array']);

    expect($a)->not->toBe($b);
});

test('a current matching report is implemented', function () {
    $contract = ToolContract::factory()->make(['version' => '1.2.0']);

    expect(ToolCompatibility::evaluate($contract, '1.2.0', $contract->schemaFingerprint()))
        ->toBe(ImplStatus::Implemented)
        ->and(ToolCompatibility::evaluate($contract, '1.3.0'))
        ->toBe(ImplStatus::Implemented);
});

test('an older reported version is outdated', function () {
    $contract = ToolContract::factory()->make(['version' => '2.0.0']);

    expect(ToolCompatibility::evaluate($contract, '1.9.0', $contract->schemaFingerprint()))
        ->toBe(ImplStatus::Outdated);
});

test('a mismatched schema fingerprint is incompatible', function () {
    $contract = ToolContract::factory()->make(['version' => '1.0.0']);

    expect(ToolCompatibility::evaluate($contract, '1.0.0', 'deadbeef'))
        ->toBe(ImplStatus::Incompatible);
});

test('a non client-side contract is not applicable', function () {
    $contract = ToolContract::factory()->make(['execution_mode' => ExecMode::Hosted]);

    expect(ToolCompatibility::evaluate($contract, '1.0.0'))
        ->toBe(ImplStatus::NotApplicable);
});
