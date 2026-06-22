<?php

use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Http\HttpResponse;
use Maac\Sdk\Testing\Compatibility;
use Maac\Sdk\Testing\SchemaValidator;

/**
 * Proves the PHP SDK decides schema validity, implementation compatibility, and
 * error parsing identically to MAAC, by running the shared contract fixture
 * suite (packages/sdk-fixtures) — the same file every supported SDK language
 * must pass. If a MAAC rule change regenerates the fixtures, this test fails
 * until the SDK is updated to match.
 *
 * @return array<string, mixed>
 */
function contractFixtures(): array
{
    $path = dirname(__DIR__, 3).'/packages/sdk-fixtures/contract.json';

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * @return array<string, array{0: array<string, mixed>}>
 */
function fixtureCases(string $section): array
{
    $cases = [];

    /** @var array<int, array<string, mixed>> $entries */
    $entries = contractFixtures()[$section];

    foreach ($entries as $index => $case) {
        $name = is_string($case['name'] ?? null) ? $case['name'] : (string) $index;
        $cases[$name] = [$case];
    }

    return $cases;
}

it('decides schema validity exactly like MAAC', function (array $case) {
    $result = SchemaValidator::validate($case['schema'], $case['payload']);

    expect($result->passes())->toBe($case['valid'])
        ->and($result->errors)->toBe($case['errors']);
})->with(fn () => fixtureCases('schema_validation'));

it('decides implementation compatibility exactly like MAAC', function (array $case) {
    $status = Compatibility::status(
        $case['reported_version'],
        $case['current_version'],
        $case['reported_fingerprint'],
        $case['current_fingerprint'],
    );

    expect($status)->toBe($case['status']);
})->with(fn () => fixtureCases('compatibility'));

it('parses every controlled error envelope', function (array $case) {
    $response = new HttpResponse(
        $case['status'],
        (string) json_encode(['error' => $case['code'], 'message' => 'Controlled failure.']),
    );

    $exception = MaacApiException::fromResponse($response);

    expect($exception->errorCode)->toBe($case['code'])
        ->and($exception->status)->toBe($case['status']);
})->with(fn () => fixtureCases('errors'));
