<?php

use App\Enums\Sensitivity;
use App\Support\Governance\PayloadMasker;

beforeEach(function () {
    $this->masker = new PayloadMasker;
});

test('text is passed through for non-sensitive levels', function () {
    expect($this->masker->maskText('hello', true, Sensitivity::Internal, true))->toBe('hello')
        ->and($this->masker->maskText('hello', true, Sensitivity::Public, true))->toBe('hello')
        ->and($this->masker->maskText(null, true, Sensitivity::Restricted, true))->toBeNull();
});

test('confidential text is redacted and restricted text is blocked', function () {
    expect($this->masker->maskText('secret', true, Sensitivity::Confidential, true))
        ->toBe(PayloadMasker::REDACTED)
        ->and($this->masker->maskText('top', true, Sensitivity::Restricted, true))
        ->toBe(PayloadMasker::BLOCKED)
        // Restricted without blocking still masks when masking is enabled.
        ->and($this->masker->maskText('top', true, Sensitivity::Restricted, false))
        ->toBe(PayloadMasker::REDACTED)
        // Confidential with masking disabled is stored verbatim.
        ->and($this->masker->maskText('secret', false, Sensitivity::Confidential, true))
        ->toBe('secret');
});

test('arrays preserve structure while redacting leaves', function () {
    $payload = ['summary' => 'x', 'nested' => ['a' => 1, 'b' => 2]];

    $masked = $this->masker->maskArray($payload, true, Sensitivity::Confidential, true);

    expect($masked['summary'])->toBe(PayloadMasker::REDACTED)
        ->and($masked['nested']['a'])->toBe(PayloadMasker::REDACTED)
        ->and($masked['nested']['b'])->toBe(PayloadMasker::REDACTED);

    expect($this->masker->maskArray($payload, true, Sensitivity::Restricted, true))
        ->toBe(['_redacted' => PayloadMasker::BLOCKED]);

    expect($this->masker->maskArray($payload, true, Sensitivity::Internal, true))->toBe($payload)
        ->and($this->masker->maskArray(null, true, Sensitivity::Restricted, true))->toBeNull();
});

test('wouldRedact reflects whether any masking applies', function () {
    expect($this->masker->wouldRedact(Sensitivity::Restricted, false, true))->toBeTrue()
        ->and($this->masker->wouldRedact(Sensitivity::Confidential, true, false))->toBeTrue()
        ->and($this->masker->wouldRedact(Sensitivity::Confidential, false, false))->toBeFalse()
        ->and($this->masker->wouldRedact(Sensitivity::Internal, true, true))->toBeFalse();
});
