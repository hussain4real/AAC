<?php

namespace App\Support\Sdk;

use DateTimeImmutable;
use JsonException;

/**
 * The versioned MAACC compact-schema dialect and its runtime validator.
 *
 * Legacy string definitions remain valid (`string`, `integer?`, `string·date`).
 * Rich definitions add nested `properties`, array `items`, enums, bounds,
 * formats, and an explicit `additionalProperties` policy. The top-level object
 * is closed by default so model/provider-added fields never reach a tool.
 */
class ToolSchema
{
    public const DIALECT = 'https://maacc.dev/schema/compact/1.0';

    public const BASE_TYPES = ['string', 'number', 'integer', 'boolean', 'object', 'array'];

    private const FORMATS = ['date', 'date-time', 'email', 'uuid', 'uri'];

    private const MAX_DEPTH = 8;

    private const MAX_PROPERTIES = 100;

    /** @return array<int, string> */
    public static function validateDefinition(mixed $schema): array
    {
        if (! is_array($schema) || $schema === [] || array_is_list($schema)) {
            return ['The schema must be a non-empty object of field definitions.'];
        }

        return self::definitionMapErrors($schema, '', 0);
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function validatePayload(array $schema, array $payload): array
    {
        return self::objectErrors($schema, $payload, '', false, 0);
    }

    /**
     * Project a validated payload to the contract-declared shape.
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function projectPayload(array $schema, array $payload): array
    {
        $projected = [];

        foreach ($schema as $field => $definition) {
            if (is_string($field) && array_key_exists($field, $payload)) {
                $projected[$field] = self::projectValue($definition, $payload[$field]);
            }
        }

        return $projected;
    }

    /**
     * Return the canonical JSON byte length, treating unencodable data as
     * larger than any supported tool payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function payloadBytes(array $payload): int
    {
        try {
            return strlen(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return PHP_INT_MAX;
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     */
    public static function payloadIsValid(array $schema, array $payload): bool
    {
        return self::validatePayload($schema, $payload) === [];
    }

    public static function baseType(mixed $definition): string
    {
        if (is_array($definition)) {
            return is_string($definition['type'] ?? null) ? trim($definition['type']) : '';
        }

        if (! is_string($definition)) {
            return '';
        }

        $base = explode('·', $definition, 2)[0];

        return trim(rtrim(trim($base), '?'));
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
     * @return array<int, string>
     */
    private static function definitionMapErrors(array $schema, string $prefix, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['Schema depth exceeds the supported maximum of '.self::MAX_DEPTH.'.'];
        }

        if (count($schema) > self::MAX_PROPERTIES) {
            return ['A schema object may define at most '.self::MAX_PROPERTIES.' properties.'];
        }

        $errors = [];

        foreach ($schema as $field => $definition) {
            if (! is_string($field) || trim($field) === '') {
                $errors[] = 'Schema field names must be non-empty strings.';

                continue;
            }

            $path = $prefix === '' ? $field : "{$prefix}.{$field}";
            $errors = [...$errors, ...self::definitionErrors($definition, $path, $depth)];
        }

        return $errors;
    }

    /** @return array<int, string> */
    private static function definitionErrors(mixed $definition, string $path, int $depth): array
    {
        if (is_string($definition)) {
            $base = self::baseType($definition);

            return in_array($base, self::BASE_TYPES, true)
                ? []
                : ["Field \"{$path}\" has an unsupported type \"{$base}\". Allowed: ".implode(', ', self::BASE_TYPES).'.'];
        }

        if (! is_array($definition) || array_is_list($definition)) {
            return ["The definition for field \"{$path}\" must be a type string or definition object."];
        }

        $allowed = [
            'type', 'required', 'format', 'enum', 'minimum', 'maximum',
            'minLength', 'maxLength', 'minItems', 'maxItems', 'items',
            'properties', 'additionalProperties',
        ];
        $errors = [];

        foreach (array_keys($definition) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                $errors[] = "Field \"{$path}\" contains unsupported schema keyword \"{$key}\".";
            }
        }

        $base = self::baseType($definition);

        if (! in_array($base, self::BASE_TYPES, true)) {
            $errors[] = "Field \"{$path}\" has an unsupported type \"{$base}\". Allowed: ".implode(', ', self::BASE_TYPES).'.';

            return $errors;
        }

        if (isset($definition['required']) && ! is_bool($definition['required'])) {
            $errors[] = "Field \"{$path}\" keyword \"required\" must be boolean.";
        }

        if (isset($definition['format']) && (! is_string($definition['format']) || ! in_array($definition['format'], self::FORMATS, true))) {
            $errors[] = "Field \"{$path}\" has an unsupported format.";
        }

        if (isset($definition['enum']) && (! is_array($definition['enum']) || $definition['enum'] === [] || ! array_is_list($definition['enum']))) {
            $errors[] = "Field \"{$path}\" keyword \"enum\" must be a non-empty list.";
        }

        foreach (['minimum', 'maximum'] as $keyword) {
            if (isset($definition[$keyword]) && ! is_int($definition[$keyword]) && ! is_float($definition[$keyword])) {
                $errors[] = "Field \"{$path}\" keyword \"{$keyword}\" must be numeric.";
            }
        }

        foreach (['minLength', 'maxLength', 'minItems', 'maxItems'] as $keyword) {
            if (isset($definition[$keyword]) && (! is_int($definition[$keyword]) || $definition[$keyword] < 0)) {
                $errors[] = "Field \"{$path}\" keyword \"{$keyword}\" must be a non-negative integer.";
            }
        }

        if ($base === 'object') {
            $properties = $definition['properties'] ?? null;

            if (! is_array($properties) || array_is_list($properties)) {
                $errors[] = "Object field \"{$path}\" must define a properties object.";
            } else {
                $errors = [...$errors, ...self::definitionMapErrors($properties, $path, $depth + 1)];
            }

            if (isset($definition['additionalProperties']) && ! is_bool($definition['additionalProperties'])) {
                $errors[] = "Object field \"{$path}\" keyword \"additionalProperties\" must be boolean.";
            }
        }

        if ($base === 'array') {
            if (! array_key_exists('items', $definition)) {
                $errors[] = "Array field \"{$path}\" must define items.";
            } else {
                $errors = [...$errors, ...self::definitionErrors($definition['items'], "{$path}[]", $depth + 1)];
            }
        }

        return $errors;
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private static function objectErrors(array $schema, array $payload, string $prefix, bool $additionalProperties, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['Payload depth exceeds the schema limit.'];
        }

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

            $errors = [...$errors, ...self::valueErrors($definition, $payload[$field], $path, $depth)];
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
    private static function valueErrors(mixed $definition, mixed $value, string $path, int $depth): array
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
                $depth + 1,
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
                    $errors = [...$errors, ...self::valueErrors($definition['items'], $item, "{$path}[{$index}]", $depth + 1)];
                }
            }
        }

        return $errors;
    }

    private static function projectValue(mixed $definition, mixed $value): mixed
    {
        if (! is_array($definition) || ! is_array($value)) {
            return $value;
        }

        if (self::baseType($definition) === 'object' && is_array($definition['properties'] ?? null)) {
            if (($definition['additionalProperties'] ?? false) === true) {
                return $value;
            }

            return self::projectPayload($definition['properties'], $value);
        }

        if (self::baseType($definition) === 'array' && array_key_exists('items', $definition)) {
            return array_map(fn (mixed $item): mixed => self::projectValue($definition['items'], $item), $value);
        }

        return $value;
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

    private static function matchesFormat(string $value, string $format): bool
    {
        return match ($format) {
            'date' => self::matchesDate($value, 'Y-m-d'),
            'date-time' => self::matchesDateTime($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };
    }

    private static function matchesDate(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private static function matchesDateTime(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
