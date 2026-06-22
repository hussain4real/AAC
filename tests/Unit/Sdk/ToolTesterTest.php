<?php

use Maac\Sdk\Resources\ManifestTool;
use Maac\Sdk\Testing\SchemaValidator;
use Maac\Sdk\Testing\ToolTester;
use Maac\Sdk\Tools\CallableToolHandler;

/**
 * Unit coverage for the SDK pre-flight test helpers — schema validation and the
 * handler tester an application runs before reporting an implementation.
 */
function manifestTool(array $input, array $output): ManifestTool
{
    return ManifestTool::fromArray([
        'name' => 'fetch-records',
        'version' => '1.0.0',
        'schema_fingerprint' => 'fp',
        'input_schema' => $input,
        'output_schema' => $output,
        'implementation' => ['status' => 'required'],
    ]);
}

it('passes a payload that satisfies the schema', function () {
    $result = SchemaValidator::validate(
        ['query' => 'string', 'limit' => 'integer?'],
        ['query' => 'today'],
    );

    expect($result->passes())->toBeTrue()
        ->and($result->errors)->toBe([]);
});

it('reports missing required fields and type mismatches', function () {
    $result = SchemaValidator::validate(
        ['query' => 'string', 'total' => 'number'],
        ['total' => 'not-a-number'],
    );

    expect($result->fails())->toBeTrue()
        ->and($result->errors)->toBe([
            'Missing required field "query".',
            'Field "total" must be of type number.',
        ]);
});

it('parses optional markers and format hints in definitions', function () {
    expect(SchemaValidator::baseType('string·date'))->toBe('string')
        ->and(SchemaValidator::baseType('number?'))->toBe('number')
        ->and(SchemaValidator::isOptional('number?'))->toBeTrue()
        ->and(SchemaValidator::isOptional('string'))->toBeFalse();
});

it('validates a handler input and output against the contract', function () {
    $tool = manifestTool(['query' => 'string'], ['records' => 'array', 'total' => 'integer']);
    $handler = new CallableToolHandler('fetch-records', fn (array $args): array => [
        'records' => ['a', 'b'],
        'total' => 2,
    ]);

    $result = (new ToolTester)->test($tool, $handler, ['query' => 'today']);

    expect($result->passes())->toBeTrue();
});

it('flags a handler whose result violates the output schema', function () {
    $tool = manifestTool(['query' => 'string'], ['records' => 'array', 'total' => 'integer']);
    $handler = new CallableToolHandler('fetch-records', fn (array $args): array => [
        'records' => ['a'],
        // total missing, and the input is wrong type too.
    ]);

    $result = (new ToolTester)->test($tool, $handler, ['query' => 123]);

    expect($result->fails())->toBeTrue()
        ->and($result->errors)->toBe([
            'input: Field "query" must be of type string.',
            'output: Missing required field "total".',
        ]);
});
