<?php

namespace App\Delivery\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class AllowedEmailDomains implements ValidationRule
{
    public function __construct(protected array $allowedDomains)
    {
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $domain = strtolower(substr(strrchr($value, '@'), 1));

        if (! \in_array($domain, array_map('strtolower', $this->allowedDomains))) {
            $fail('The :attribute domain is not permitted.');
        }
    }
}
