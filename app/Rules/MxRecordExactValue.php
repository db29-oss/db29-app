<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MxRecordExactValue implements ValidationRule
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
        $dns_get_record = dns_get_record($value, DNS_MX);

        if ($dns_get_record === false) {
            $fail(__('trans.invalid_mx_hostname'));
            return;
        }

        if (count($dns_get_record) === 0) {
            $fail(__('trans.mx_record_not_found'));
            return;
        }

        if ($dns_get_record[0]['target'] !== $this->exact_value) {
            $fail(__('trans.invalid_exact_mail_mx_record'));
        }
    }
}
