<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnameRecordExactValue implements ValidationRule
{
    public function __construct(
        private string $exact_value,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dns_get_record = dns_get_record($value, DNS_CNAME);

        if ($dns_get_record === false) {
            $fail(__('trans.unable_get_cname_record'));
            return;
        }

        if (count($dns_get_record) === 0) {
            $fail(__('trans.record_cname_not_found'));
            return;
        }

        if ($dns_get_record[0]['target'] !== $this->exact_value) {
            $fail(__('trans.invalid_exact_cname_record'));
        }
    }
}
