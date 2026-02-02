<?php

namespace App\Delivery\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class ValidDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // FILTER_VALIDATE_DOMAIN ensures correct hostname format
        if (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($value, '.')) {
            $fail('The :attribute must be a valid domain (e.g., example.com).');
        }

        // Optional: Block IPs if you only want named domains
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            $fail('The :attribute must be a domain name, not an IP address.');
        }
    }
}
