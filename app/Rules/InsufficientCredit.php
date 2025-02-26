<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InsufficientCredit implements ValidationRule
{
    public function __construct(
        private string $credit,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value < $this->credit) {
            $fail(__('trans.insufficient_credit', ['lack_credit' => formatNumberShort($this->credit - $value)]));
        }
    }
}
