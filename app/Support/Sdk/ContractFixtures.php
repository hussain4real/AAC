<?php

namespace App\Support\Sdk;

/**
 * Builds the canonical SDK contract fixture suite from MAAC's own logic (Phase
 * 6C). Each case's expected output is computed by the real server classes
 * ({@see ToolSchema}, {@see ToolCompatibility}, {@see SdkPlatform}), so the
 * committed `packages/sdk-fixtures/contract.json` IS MAAC's source of truth.
 *
 * Every supported SDK language runs the same file through its own port of these
 * rules; a server-side change to a schema/compatibility/negotiation rule (or an
 * error envelope) shifts the generated fixtures, the drift check fails CI, and
 * the regenerated fixtures then fail any SDK that has not been updated to match
 * — the tripwire that stops a contract change from silently breaking clients.
 */
class ContractFixtures
{
    /**
     * The controlled SDK/runtime error codes and their HTTP statuses. The SDK
     * error parser must map each `{error, message}` envelope to this code/status.
     *
     * @var array<int, array{code: string, status: int}>
     */
    private const ERROR_MATRIX = [
        ['code' => 'invalid_token', 'status' => 401],
        ['code' => 'unknown_client', 'status' => 401],
        ['code' => 'credential_revoked', 'status' => 403],
        ['code' => 'agent_not_found', 'status' => 404],
        ['code' => 'run_not_found', 'status' => 404],
        ['code' => 'agent_not_published', 'status' => 409],
        ['code' => 'run_not_waiting', 'status' => 409],
        ['code' => 'payload_too_large', 'status' => 413],
        ['code' => 'invalid_tool_result', 'status' => 422],
        ['code' => 'quota_exceeded', 'status' => 429],
    ];

    /**
     * Build the full canonical fixture dataset.
     *
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return [
            'api_version' => (new SdkPlatform)->apiVersion(),
            'schema_validation' => self::schemaValidationCases(),
            'fingerprint' => self::fingerprintCases(),
            'compatibility' => self::compatibilityCases(),
            'version_negotiation' => self::versionNegotiationCases(),
            'errors' => array_map(
                static fn (array $entry): array => ['code' => $entry['code'], 'status' => $entry['status']],
                self::ERROR_MATRIX,
            ),
        ];
    }

    /**
     * Serialize the fixtures to deterministic, pretty-printed JSON (with a
     * trailing newline) for byte-stable drift comparison.
     */
    public static function toJson(): string
    {
        return json_encode(
            self::build(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    /**
     * Schema-validation cases, with the valid flag + errors computed by MAAC's
     * real {@see ToolSchema::validatePayload()}.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function schemaValidationCases(): array
    {
        $cases = [
            [
                'name' => 'all base types valid',
                'schema' => ['s' => 'string', 'n' => 'number', 'i' => 'integer', 'b' => 'boolean', 'o' => 'object', 'a' => 'array'],
                'payload' => ['s' => 'x', 'n' => 1.5, 'i' => 2, 'b' => true, 'o' => ['k' => 'v'], 'a' => [1, 2]],
            ],
            [
                'name' => 'optional field omitted is valid',
                'schema' => ['query' => 'string', 'limit' => 'integer?'],
                'payload' => ['query' => 'today'],
            ],
            [
                'name' => 'missing required field',
                'schema' => ['query' => 'string', 'total' => 'number'],
                'payload' => ['total' => 5],
            ],
            [
                'name' => 'wrong scalar type',
                'schema' => ['n' => 'number'],
                'payload' => ['n' => 'nope'],
            ],
            [
                'name' => 'integer rejects a float',
                'schema' => ['i' => 'integer'],
                'payload' => ['i' => 1.5],
            ],
            [
                'name' => 'boolean is not a number',
                'schema' => ['n' => 'number'],
                'payload' => ['n' => true],
            ],
            [
                'name' => 'object rejects a list',
                'schema' => ['o' => 'object'],
                'payload' => ['o' => [1, 2]],
            ],
            [
                'name' => 'array rejects an object',
                'schema' => ['a' => 'array'],
                'payload' => ['a' => ['k' => 'v']],
            ],
            [
                'name' => 'format hint is ignored',
                'schema' => ['d' => 'string·date'],
                'payload' => ['d' => '2026-01-01'],
            ],
            [
                'name' => 'extra fields are tolerated',
                'schema' => ['query' => 'string'],
                'payload' => ['query' => 'hi', 'extra' => true],
            ],
        ];

        return array_map(static function (array $case): array {
            $errors = ToolSchema::validatePayload($case['schema'], $case['payload']);

            return [...$case, 'valid' => $errors === [], 'errors' => array_values($errors)];
        }, $cases);
    }

    /**
     * Fingerprint cases, with the value computed by MAAC's real
     * {@see ToolCompatibility::fingerprint()}.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function fingerprintCases(): array
    {
        $cases = [
            [
                'name' => 'basic shape',
                'input' => ['query' => 'string'],
                'output' => ['records' => 'array', 'total' => 'number'],
            ],
            [
                'name' => 'reordered keys match the basic shape',
                'input' => ['query' => 'string'],
                'output' => ['total' => 'number', 'records' => 'array'],
            ],
            [
                'name' => 'insignificant whitespace is ignored',
                'input' => ['query' => ' string '],
                'output' => ['records' => 'array', 'total' => 'number'],
            ],
            [
                'name' => 'a changed type changes the fingerprint',
                'input' => ['query' => 'number'],
                'output' => ['records' => 'array', 'total' => 'number'],
            ],
        ];

        return array_map(static function (array $case): array {
            return [...$case, 'fingerprint' => ToolCompatibility::fingerprint($case['input'], $case['output'])];
        }, $cases);
    }

    /**
     * Implementation-compatibility cases, with the status computed by MAAC's
     * real {@see ToolCompatibility::status()}.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function compatibilityCases(): array
    {
        $cases = [
            ['name' => 'current matching report is implemented', 'reported_version' => '1.2.0', 'current_version' => '1.2.0', 'reported_fingerprint' => 'fp-a', 'current_fingerprint' => 'fp-a'],
            ['name' => 'a newer reported version is implemented', 'reported_version' => '1.3.0', 'current_version' => '1.2.0', 'reported_fingerprint' => null, 'current_fingerprint' => 'fp-a'],
            ['name' => 'an older reported version is outdated', 'reported_version' => '1.0.0', 'current_version' => '2.0.0', 'reported_fingerprint' => 'fp-a', 'current_fingerprint' => 'fp-a'],
            ['name' => 'a mismatched fingerprint is incompatible', 'reported_version' => '2.0.0', 'current_version' => '2.0.0', 'reported_fingerprint' => 'fp-old', 'current_fingerprint' => 'fp-new'],
            ['name' => 'a null reported fingerprint skips the check', 'reported_version' => '2.0.0', 'current_version' => '2.0.0', 'reported_fingerprint' => null, 'current_fingerprint' => 'fp-a'],
        ];

        return array_map(static function (array $case): array {
            $status = ToolCompatibility::status(
                (string) $case['reported_version'],
                (string) $case['current_version'],
                $case['reported_fingerprint'],
                $case['current_fingerprint'],
            );

            return [...$case, 'status' => $status->value];
        }, $cases);
    }

    /**
     * Version-negotiation cases, with the verdict computed by MAAC's real
     * {@see SdkPlatform::resolveCompatibility()}.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function versionNegotiationCases(): array
    {
        $cases = [
            ['name' => 'within the window is compatible', 'reported' => '1.2.0', 'minimum' => '1.0.0', 'current' => '1.4.0'],
            ['name' => 'at the exact minimum is compatible', 'reported' => '1.0.0', 'minimum' => '1.0.0', 'current' => '1.4.0'],
            ['name' => 'below the minimum requires an upgrade', 'reported' => '0.9.0', 'minimum' => '1.0.0', 'current' => '1.4.0'],
            ['name' => 'ahead of current is compatible', 'reported' => '2.0.0', 'minimum' => '1.0.0', 'current' => '1.4.0'],
            ['name' => 'unreported is unknown', 'reported' => null, 'minimum' => '1.0.0', 'current' => '1.4.0'],
        ];

        return array_map(static function (array $case): array {
            $verdict = SdkPlatform::resolveCompatibility(
                $case['reported'],
                (string) $case['minimum'],
                (string) $case['current'],
                '1.0.0',
            );

            return [
                ...$case,
                'status' => $verdict['status'],
                'compatible' => $verdict['compatible'],
                'upgrade_required' => $verdict['upgrade_required'],
            ];
        }, $cases);
    }
}
