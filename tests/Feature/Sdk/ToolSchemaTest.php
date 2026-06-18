<?php

use App\Support\Sdk\ToolSchema;

test('a well-formed schema definition passes validation', function () {
    $errors = ToolSchema::validateDefinition([
        'from_date' => 'string·date',
        'limit' => 'number?',
        'active' => 'boolean',
        'meta' => 'object',
        'tags' => 'array',
    ]);

    expect($errors)->toBe([]);
});

test('a non-array or empty schema definition is rejected', function () {
    expect(ToolSchema::validateDefinition('nope'))->toHaveCount(1)
        ->and(ToolSchema::validateDefinition([]))->toHaveCount(1);
});

test('schema definitions reject unsupported types and malformed entries', function () {
    $errors = ToolSchema::validateDefinition([
        'good' => 'string',
        'bad_type' => 'datetime',
        'not_a_string' => ['nested'],
    ]);

    expect($errors)->toHaveCount(2)
        ->and(implode(' ', $errors))->toContain('datetime')
        ->and(implode(' ', $errors))->toContain('not_a_string');
});

test('schema definitions reject blank field names', function () {
    expect(ToolSchema::validateDefinition(['' => 'string']))
        ->toContain('Schema field names must be non-empty strings.');
});

test('a payload satisfying the schema validates', function () {
    $schema = [
        'from_date' => 'string·date',
        'limit' => 'number?',
        'vessel_id' => 'string?',
        'active' => 'boolean',
        'records' => 'array',
        'summary' => 'object',
        'count' => 'integer',
    ];

    $payload = [
        'from_date' => '2026-01-01',
        'active' => true,
        'records' => ['a', 'b'],
        'summary' => ['ok' => true],
        'count' => 5,
    ];

    expect(ToolSchema::payloadIsValid($schema, $payload))->toBeTrue()
        ->and(ToolSchema::validatePayload($schema, $payload))->toBe([]);
});

test('a payload missing required fields or with wrong types is rejected', function () {
    $schema = [
        'from_date' => 'string·date',
        'limit' => 'number?',
        'active' => 'boolean',
        'records' => 'array',
        'summary' => 'object',
    ];

    $errors = ToolSchema::validatePayload($schema, [
        // from_date missing (required)
        'limit' => 'ten',      // optional but wrong type
        'active' => 'yes',     // wrong type
        'records' => ['ok' => 1], // object, not list
        'summary' => [1, 2],   // list, not object
    ]);

    expect($errors)->toHaveCount(5)
        ->and(ToolSchema::payloadIsValid($schema, []))->toBeFalse();
});

test('integer and number types are distinguished and booleans are not numbers', function () {
    expect(ToolSchema::payloadIsValid(['n' => 'integer'], ['n' => 3]))->toBeTrue()
        ->and(ToolSchema::payloadIsValid(['n' => 'integer'], ['n' => 3.5]))->toBeFalse()
        ->and(ToolSchema::payloadIsValid(['n' => 'integer'], ['n' => true]))->toBeFalse()
        ->and(ToolSchema::payloadIsValid(['n' => 'number'], ['n' => 3.5]))->toBeTrue()
        ->and(ToolSchema::payloadIsValid(['n' => 'number'], ['n' => true]))->toBeFalse();
});

test('empty arrays satisfy both object and array types', function () {
    expect(ToolSchema::payloadIsValid(['x' => 'object'], ['x' => []]))->toBeTrue()
        ->and(ToolSchema::payloadIsValid(['x' => 'array'], ['x' => []]))->toBeTrue();
});

test('base type and optionality are parsed from a definition', function () {
    expect(ToolSchema::baseType('string·date'))->toBe('string')
        ->and(ToolSchema::baseType('number?'))->toBe('number')
        ->and(ToolSchema::isOptional('number?'))->toBeTrue()
        ->and(ToolSchema::isOptional('string·date'))->toBeFalse();
});
