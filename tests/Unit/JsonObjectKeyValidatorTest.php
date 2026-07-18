<?php

use App\Support\Sdk\JsonObjectKeyValidator;

test('valid nested json is accepted', function () {
    $json = '{"input":"status","context":{"roles":["viewer"],"active":true},"count":2}';

    expect(app(JsonObjectKeyValidator::class)->validate($json))->toBeTrue();
});

test('duplicate keys are rejected at any object depth', function (string $json) {
    expect(app(JsonObjectKeyValidator::class)->validate($json))->toBeFalse();
})->with([
    'top-level duplicate' => '{"input":"one","input":"two"}',
    'nested duplicate' => '{"context":{"role":"viewer","role":"admin"}}',
    'escaped equivalent' => '{"role":"viewer","r\\u006fle":"admin"}',
    'object nested in array' => '[{"id":1,"id":2}]',
]);

test('malformed or excessively nested json is rejected', function () {
    $tooDeep = str_repeat('[', 66).str_repeat(']', 66);

    expect(app(JsonObjectKeyValidator::class)->validate('{"input":}'))->toBeFalse()
        ->and(app(JsonObjectKeyValidator::class)->validate('{"input":"unterminated}'))->toBeFalse()
        ->and(app(JsonObjectKeyValidator::class)->validate('{"input":true} trailing'))->toBeFalse()
        ->and(app(JsonObjectKeyValidator::class)->validate($tooDeep))->toBeFalse();
});
