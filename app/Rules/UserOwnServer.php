<?php

namespace App\Rules;

use App\Models\Machine;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserOwnServer implements ValidationRule
{
    public function __construct(
        private Machine|null $machine,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->machine === null) {
            $fail(__('trans.server_not_found'));
        }
    }
}
