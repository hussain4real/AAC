<?php

namespace App\Support\Sdk;

/**
 * Validates MAAC tool contract schemas and the payloads exchanged against them.
 *
 * A schema is the prototype's compact field map: an associative array of
 * `field name => type definition`, where a type definition is
 * `<base>[?][·<format>]` — e.g. `string`, `number?`, `string·date`. A trailing
 * `?` marks the field optional; everything after `·` is a (purely descriptive)
 * format hint. This is the single source of truth for both contract-definition
 * validation (is the declared schema well-formed?) and runtime boundary
 * validation (does an argument/result payload satisfy the schema?).
 */
class ToolSchema
{
    /**
     * Base types accepted in a tool contract schema definition.
     *
     * @var array<int, string>
     */
    public const BASE_TYPES = ['string', 'number', 'integer', 'boolean', 'object', 'array'];

    /**
     * Validate that a schema definition is well-formed, returning a list of
     * human-readable error strings (empty when the definition is valid).
     *
     * @return array<int, string>
     */
    public static function validateDefinition(mixed $schema): array
    {
        if (! is_array($schema) || $schema === []) {
            return ['The schema must be a non-empty object of field definitions.'];
        }

        $errors = [];

        foreach ($schema as $field => $definition) {
            if (! is_string($field) || trim($field) === '') {
                $errors[] = 'Schema field names must be non-empty strings.';

                continue;
            }

            if (! is_string($definition)) {
                $errors[] = "The type for field \"{$field}\" must be a string.";

                continue;
            }

            $base = self::baseType($definition);

            if (! in_array($base, self::BASE_TYPES, true)) {
                $errors[] = "Field \"{$field}\" has an unsupported type \"{$base}\". Allowed: ".implode(', ', self::BASE_TYPES).'.';
            }
        }

        return $errors;
    }

    /**
     * Validate a payload against a schema, returning a list of error strings
     * (empty when the payload satisfies the schema). Unknown extra fields are
     * tolerated so a contract can add fields without breaking older callers.
     *
     * @param  array<string, string>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function validatePayload(array $schema, array $payload): array
    {
        $errors = [];

        foreach ($schema as $field => $definition) {
            $present = array_key_exists($field, $payload);

            if (! $present) {
                if (! self::isOptional($definition)) {
                    $errors[] = "Missing required field \"{$field}\".";
                }

                continue;
            }

            $base = self::baseType($definition);

            if (! self::valueMatchesType($payload[$field], $base)) {
                $errors[] = "Field \"{$field}\" must be of type {$base}.";
            }
        }

        return $errors;
    }

    /**
     * Determine whether a payload satisfies the schema.
     *
     * @param  array<string, string>  $schema
     * @param  array<string, mixed>  $payload
     */
    public static function payloadIsValid(array $schema, array $payload): bool
    {
        return self::validatePayload($schema, $payload) === [];
    }

    /**
     * Extract the base type from a type definition (stripping the optional
     * marker and any format hint).
     */
    public static function baseType(string $definition): string
    {
        $base = explode('·', $definition, 2)[0];

        return trim(rtrim(trim($base), '?'));
    }

    /**
     * Determine whether a field definition marks the field optional.
     */
    public static function isOptional(string $definition): bool
    {
        return str_contains(explode('·', $definition, 2)[0], '?');
    }

    /**
     * Check a runtime value against a schema base type.
     */
    private static function valueMatchesType(mixed $value, string $base): bool
    {
        return match ($base) {
            'string' => is_string($value),
            // is_int/is_float are already false for booleans in PHP.
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            default => false,
        };
    }
}
