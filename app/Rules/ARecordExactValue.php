<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ARecordExactValue implements ValidationRule
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
        $dns_get_record = dns_get_record($value, DNS_A);

        if ($dns_get_record === false) {
            $fail(__('trans.unable_get_a_record'));
            return;
        }

        if (count($dns_get_record) === 0) {
            $fail(__('trans.record_a_not_found'));
            return;
        }

        if ($dns_get_record[0]['ip'] !== $this->exact_value) {
            $fail(__('trans.invalid_exact_a_record'));
        }
    }
}
