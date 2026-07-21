<?php

namespace App\Rules;

use App\Exceptions\OutboundRequestBlocked;
use App\Support\Outbound\OutboundRequestPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafeOutboundUrl implements ValidationRule
{
    /** @param  array<int, string>  $allowedHosts */
    public function __construct(
        private readonly OutboundRequestPolicy $policy,
        private readonly string $purpose,
        private readonly array $allowedHosts = [],
        private readonly bool $requireHttps = true,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($this->requireHttps && parse_url($value, PHP_URL_SCHEME) !== 'https')) {
            $fail('The :attribute must be a safe public HTTPS URL.');

            return;
        }

        try {
            $this->policy->inspect($value, $this->purpose, $this->allowedHosts);
        } catch (OutboundRequestBlocked $exception) {
            $fail("The :attribute is not an approved public destination ({$exception->reason}).");
        }
    }
}
