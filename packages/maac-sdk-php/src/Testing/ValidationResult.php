<?php

declare(strict_types=1);

namespace Maac\Sdk\Testing;

/**
 * The outcome of validating a payload (or a handler's input/output) against a
 * MAAC tool contract schema: whether it is valid and, if not, the list of
 * human-readable problems.
 */
final class ValidationResult
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
    ) {}

    /**
     * Build a result from a list of error strings (valid when empty).
     *
     * @param  array<int, string>  $errors
     */
    public static function fromErrors(array $errors): self
    {
        $errors = array_values($errors);

        return new self($errors === [], $errors);
    }

    /**
     * Whether the payload satisfied the schema.
     */
    public function passes(): bool
    {
        return $this->valid;
    }

    /**
     * Whether the payload violated the schema.
     */
    public function fails(): bool
    {
        return ! $this->valid;
    }
}
