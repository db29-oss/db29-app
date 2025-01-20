<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Ipv4OrDomainARecordExists implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (checkdnsrr($value, 'A') === false && ! filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $fail(__('trans.ipv4_or_domain_a_record_must_exists'));
        }
    }
}
