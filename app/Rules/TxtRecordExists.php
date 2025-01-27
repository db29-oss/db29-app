<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TxtRecordExists implements ValidationRule
{
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
    }
}
