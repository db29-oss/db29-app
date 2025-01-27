<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TxtRecordExactValue implements ValidationRule
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
        $dns_get_record = dns_get_record($value, DNS_TXT);

        if ($dns_get_record === false) {
            $fail(__('trans.invalid_txt_hostname'));
            return;
        }

        if (count($dns_get_record) === 0) {
            $fail(__('trans.txt_record_not_found'));
            return;
        }

        $exact_public_txt = "p=".preg_replace('/-----.*?-----|\r?\n/', '', $this->exact_value);

        if ($dns_get_record[0]['txt'] !== $exact_public_txt) {
            $fail(__('trans.invalid_exact_txt_record'));
        }
    }
}
