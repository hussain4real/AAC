<?php

namespace App\Rules;

use App\Support\Sdk\ToolSchema;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a tool contract input/output schema is a well-formed map of
 * field definitions using only supported base types.
 */
class ValidToolSchema implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (ToolSchema::validateDefinition($value) as $error) {
            $fail($error);
        }
    }
}
