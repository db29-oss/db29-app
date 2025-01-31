<?php

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;

class SSHPrivateKeyRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $private_key = RSA::load($value);
        } catch (NoKeyLoadedException) {
            try {
                $private_key = EC::load($value);
            } catch (NoKeyLoadedException $e) {
                $fail(__('trans.invalid_private_key'));
            }
        } catch (Exception) {
            $fail(__('trans.invalid_private_key'));
        }
    }
}
