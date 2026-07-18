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

test('empty collections scalar values whitespace and escaped strings are accepted', function () {
    $validator = app(JsonObjectKeyValidator::class);

    expect($validator->validate(" \n {\"empty_object\":{},\"empty_array\":[],\"escaped\":\"a\\\\b\",\"null\":null} \t"))->toBeTrue()
        ->and($validator->validate('[true,false,1,-2.5,null]'))->toBeTrue();
});

test('object array and string delimiter edge cases are rejected', function (string $json) {
    expect(app(JsonObjectKeyValidator::class)->validate($json))->toBeFalse();
})->with([
    'object key is not a string' => '{1:true}',
    'missing colon' => '{"key" true}',
    'missing object comma' => '{"one":1 "two":2}',
    'missing array comma' => '[1 2]',
    'unescaped control character' => "{\"key\":\"bad\nvalue\"}",
    'missing scalar value' => '{"key":}',
]);
