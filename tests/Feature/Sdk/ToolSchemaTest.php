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

test('the versioned compact dialect enforces nested arrays enums bounds formats and closed objects', function () {
    $schema = [
        'request' => [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 64],
                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'tags' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 2, 'items' => ['type' => 'string', 'enum' => ['ops', 'safety']]],
            ],
            'additionalProperties' => false,
        ],
    ];

    expect(ToolSchema::DIALECT)->toEndWith('/compact/1.0')
        ->and(ToolSchema::validateDefinition($schema))->toBe([])
        ->and(ToolSchema::validatePayload($schema, [
            'request' => ['email' => 'ops@example.com', 'priority' => 3, 'tags' => ['ops']],
        ]))->toBe([]);

    $errors = ToolSchema::validatePayload($schema, [
        'request' => ['email' => 'bad', 'priority' => 9, 'tags' => ['ops', 'other', 'safety'], 'admin' => true],
        'top_level_extra' => true,
    ]);

    expect(implode(' ', $errors))
        ->toContain('format email')
        ->toContain('exceeds maximum')
        ->toContain('exceeds maxItems')
        ->toContain('enum values')
        ->toContain('request.admin')
        ->toContain('top_level_extra');
});

test('schema definitions reject malformed rich definitions and unsupported keywords', function () {
    $errors = ToolSchema::validateDefinition([
        'object' => ['type' => 'object', 'properties' => [], 'additionalProperties' => 'yes'],
        'array' => ['type' => 'array'],
        'bad' => ['type' => 'string', 'format' => 'custom', 'magic' => true],
    ]);

    expect(implode(' ', $errors))
        ->toContain('additionalProperties')
        ->toContain('must define items')
        ->toContain('unsupported format')
        ->toContain('unsupported schema keyword');
});

test('payload projection removes undeclared nested properties before execution', function () {
    $schema = [
        'request' => [
            'type' => 'object',
            'properties' => ['query' => 'string'],
            'additionalProperties' => false,
        ],
    ];

    expect(ToolSchema::projectPayload($schema, [
        'request' => ['query' => 'ports', 'admin' => true],
        'outside' => 'drop',
    ]))->toBe(['request' => ['query' => 'ports']]);
});

test('schema limits and malformed rich keywords are rejected', function () {
    $tooMany = [];

    foreach (range(1, 101) as $index) {
        $tooMany["field_{$index}"] = 'string';
    }

    $deep = ['type' => 'object', 'properties' => ['value' => 'string']];

    foreach (range(1, 9) as $index) {
        $deep = ['type' => 'object', 'properties' => ["level_{$index}" => $deep]];
    }

    $errors = [
        ...ToolSchema::validateDefinition($tooMany),
        ...ToolSchema::validateDefinition(['deep' => $deep]),
        ...ToolSchema::validateDefinition([
            'missing_type' => ['required' => true],
            'required' => ['type' => 'string', 'required' => 'yes'],
            'enum' => ['type' => 'string', 'enum' => []],
            'minimum' => ['type' => 'number', 'minimum' => 'zero'],
            'length' => ['type' => 'string', 'minLength' => -1],
        ]),
    ];

    expect(implode(' ', $errors))
        ->toContain('at most 100 properties')
        ->toContain('depth exceeds')
        ->toContain('unsupported type')
        ->toContain('required')
        ->toContain('enum')
        ->toContain('must be numeric')
        ->toContain('non-negative integer')
        ->and(ToolSchema::baseType(null))->toBe('')
        ->and(ToolSchema::isOptional(['type' => 'string', 'required' => false]))->toBeTrue();
});

test('payload validation covers bounds formats nesting and projection edge cases', function () {
    $schema = [
        'short' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 5],
        'too_short' => ['type' => 'string', 'minLength' => 3],
        'low' => ['type' => 'number', 'minimum' => 2],
        'few' => ['type' => 'array', 'minItems' => 2, 'items' => 'integer'],
        'date_time' => ['type' => 'string', 'format' => 'date-time'],
        'uuid' => ['type' => 'string', 'format' => 'uuid'],
        'uri' => ['type' => 'string', 'format' => 'uri'],
    ];

    $errors = ToolSchema::validatePayload($schema, [
        'short' => 'excess',
        'too_short' => 'x',
        'low' => 1,
        'few' => [1],
        'date_time' => 'bad',
        'uuid' => 'bad',
        'uri' => 'bad',
    ]);

    expect(implode(' ', $errors))
        ->toContain('exceeds maxLength')
        ->toContain('shorter than minLength')
        ->toContain('below minimum')
        ->toContain('fewer than minItems')
        ->toContain('date-time')
        ->toContain('uuid')
        ->toContain('uri')
        ->and(ToolSchema::validatePayload(['date' => 'string·date'], ['date' => '2026-99-99']))
        ->toContain('Field "date" must match format date.')
        ->and(ToolSchema::payloadBytes(['invalid' => "\xB1\x31"]))->toBe(PHP_INT_MAX)
        ->and(ToolSchema::projectPayload([
            'open' => ['type' => 'object', 'properties' => ['known' => 'string'], 'additionalProperties' => true],
            'list' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['known' => 'string']]],
            'plain' => ['type' => 'array'],
        ], [
            'open' => ['known' => 'yes', 'extra' => true],
            'list' => [['known' => 'yes', 'extra' => true]],
            'plain' => ['unchanged'],
        ]))->toBe([
            'open' => ['known' => 'yes', 'extra' => true],
            'list' => [['known' => 'yes']],
            'plain' => ['unchanged'],
        ]);
});

test('payload validation guards malformed numeric schema keys and excessive depth', function () {
    $nestedDefinition = 'string';
    $nestedPayload = 'value';

    foreach (range(1, 10) as $index) {
        $nestedDefinition = ['type' => 'object', 'properties' => ['next' => $nestedDefinition]];
        $nestedPayload = ['next' => $nestedPayload];
    }

    $errors = ToolSchema::validatePayload([
        0 => 'string',
        'nested' => $nestedDefinition,
    ], [
        'nested' => $nestedPayload,
    ]);

    expect(implode(' ', $errors))->toContain('Payload depth exceeds');
});
