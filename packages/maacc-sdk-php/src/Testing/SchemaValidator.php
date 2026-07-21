<?php

declare(strict_types=1);

namespace Maacc\Sdk\Testing;

use DateTimeImmutable;

/** Mirrors the versioned MAACC compact-schema runtime validator. */
final class SchemaValidator
{
    public const DIALECT = 'https://maacc.dev/schema/compact/1.0';

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<string, mixed>  $payload
     */
    public static function validate(array $schema, array $payload): ValidationResult
    {
        return ValidationResult::fromErrors(self::objectErrors($schema, $payload, '', false));
    }

    public static function baseType(mixed $definition): string
    {
        if (is_array($definition)) {
            return is_string($definition['type'] ?? null) ? trim($definition['type']) : '';
        }

        if (! is_string($definition)) {
            return '';
        }

        return trim(rtrim(trim(explode('·', $definition, 2)[0]), '?'));
    }

    public static function isOptional(mixed $definition): bool
    {
        if (is_array($definition)) {
            return ($definition['required'] ?? true) === false;
        }

        return is_string($definition) && str_contains(explode('·', $definition, 2)[0], '?');
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private static function objectErrors(array $schema, array $payload, string $prefix, bool $additionalProperties): array
    {
        $errors = [];

        foreach ($schema as $field => $definition) {
            if (! is_string($field)) {
                continue;
            }

            $path = $prefix === '' ? $field : "{$prefix}.{$field}";

            if (! array_key_exists($field, $payload)) {
                if (! self::isOptional($definition)) {
                    $errors[] = "Missing required field \"{$path}\".";
                }

                continue;
            }

            $errors = [...$errors, ...self::valueErrors($definition, $payload[$field], $path)];
        }

        if (! $additionalProperties) {
            foreach (array_diff(array_keys($payload), array_keys($schema)) as $extra) {
                $path = $prefix === '' ? (string) $extra : "{$prefix}.{$extra}";
                $errors[] = "Field \"{$path}\" is not declared by the schema.";
            }
        }

        return $errors;
    }

    /** @return array<int, string> */
    private static function valueErrors(mixed $definition, mixed $value, string $path): array
    {
        $base = self::baseType($definition);

        if (! self::valueMatchesType($value, $base)) {
            return ["Field \"{$path}\" must be of type {$base}."];
        }

        if (! is_array($definition)) {
            $format = is_string($definition) ? (explode('·', $definition, 2)[1] ?? null) : null;

            return is_string($format) && ! self::matchesFormat($value, $format)
                ? ["Field \"{$path}\" must match format {$format}."]
                : [];
        }

        $errors = [];

        if (isset($definition['enum']) && is_array($definition['enum']) && ! in_array($value, $definition['enum'], true)) {
            $errors[] = "Field \"{$path}\" must be one of the declared enum values.";
        }

        if (is_string($value)) {
            $length = mb_strlen($value);

            if (isset($definition['minLength']) && $length < $definition['minLength']) {
                $errors[] = "Field \"{$path}\" is shorter than minLength.";
            }

            if (isset($definition['maxLength']) && $length > $definition['maxLength']) {
                $errors[] = "Field \"{$path}\" exceeds maxLength.";
            }

            if (isset($definition['format']) && ! self::matchesFormat($value, (string) $definition['format'])) {
                $errors[] = "Field \"{$path}\" must match format {$definition['format']}.";
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($definition['minimum']) && $value < $definition['minimum']) {
                $errors[] = "Field \"{$path}\" is below minimum.";
            }

            if (isset($definition['maximum']) && $value > $definition['maximum']) {
                $errors[] = "Field \"{$path}\" exceeds maximum.";
            }
        }

        if ($base === 'object' && is_array($value) && is_array($definition['properties'] ?? null)) {
            $errors = [...$errors, ...self::objectErrors(
                $definition['properties'],
                $value,
                $path,
                ($definition['additionalProperties'] ?? false) === true,
            )];
        }

        if ($base === 'array' && is_array($value)) {
            if (isset($definition['minItems']) && count($value) < $definition['minItems']) {
                $errors[] = "Field \"{$path}\" has fewer than minItems entries.";
            }

            if (isset($definition['maxItems']) && count($value) > $definition['maxItems']) {
                $errors[] = "Field \"{$path}\" exceeds maxItems.";
            }

            if (array_key_exists('items', $definition)) {
                foreach ($value as $index => $item) {
                    $errors = [...$errors, ...self::valueErrors($definition['items'], $item, "{$path}[{$index}]")];
                }
            }
        }

        return $errors;
    }

    private static function valueMatchesType(mixed $value, string $base): bool
    {
        return match ($base) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            default => false,
        };
    }

    private static function matchesFormat(mixed $value, string $format): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return match ($format) {
            'date' => self::matchesDate($value),
            'date-time' => DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value) !== false,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };
    }

    private static function matchesDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
