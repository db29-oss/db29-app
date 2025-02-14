<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Pdp\Domain;
use Pdp\SyntaxError;
use Pdp\TopLevelDomains;

class ValidDomainFormat implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $regex =
            '/^(?=.{1,253}$)'.
            '(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)'.
            '+[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])$/';

        if (! preg_match($regex, $value)) {
            $fail(__('trans.invalid_domain_name'));
        }
    }
}
