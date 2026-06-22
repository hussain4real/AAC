<?php

declare(strict_types=1);

namespace Maac\Sdk\Testing;

/**
 * Validates a payload against a MAAC tool contract schema, mirroring MAAC's
 * server-side `ToolSchema` exactly (same rules, same messages). A MAAC schema is
 * the compact field map `field => '<base>[?][·format]'` — a trailing `?` marks
 * the field optional and everything after `·` is a descriptive format hint.
 *
 * This lets an application check a local handler's arguments and result against
 * the contract *before* reporting the handler as implemented, instead of finding
 * out at runtime via an `invalid_tool_result`. The shared contract fixture suite
 * (packages/sdk-fixtures) keeps this in lock-step with the server.
 */
final class SchemaValidator
{
    /**
     * Validate a payload against a schema map.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     */
    public static function validate(array $schema, array $payload): ValidationResult
    {
        $errors = [];

        foreach ($schema as $field => $definition) {
            if (! is_string($definition)) {
                continue;
            }

            if (! array_key_exists($field, $payload)) {
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

        return ValidationResult::fromErrors($errors);
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
