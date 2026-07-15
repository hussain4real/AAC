<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PublicHttpsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The :attribute must be a valid HTTPS URL.');

            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if ($scheme !== 'https' || ! is_string($host) || $host === '') {
            $fail('The :attribute must use HTTPS.');

            return;
        }

        $normalizedHost = strtolower(rtrim($host, '.'));
        $blockedHosts = ['localhost', 'metadata.google.internal', '169.254.169.254'];

        if (in_array($normalizedHost, $blockedHosts, true) || str_ends_with($normalizedHost, '.localhost')) {
            $fail('The :attribute must use a public identity-provider host.');

            return;
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false
            && filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $fail('The :attribute must not target a private or reserved address.');
        }
    }
}
