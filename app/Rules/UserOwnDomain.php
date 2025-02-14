<?php

namespace App\Rules;

use App\Models\Instance;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserOwnDomain implements ValidationRule
{
    public function __construct(
        private string $subdomain,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $instance = Instance::where('subdomain', $this->subdomain)->with('machine')->first();

        $dns_get_record = dns_get_record($value, DNS_A);

        if (count($dns_get_record) > 0) {
            if ($dns_get_record[0]['ip'] === $instance->machine->ip_address) {
                return;
            }
        }

        $dns_get_record = dns_get_record($value, DNS_CNAME);

        if (count($dns_get_record) > 0) {
            if ($dns_get_record[0]['target'] === $instance->subdomain.'.'.config('app.domain')) {
                return;
            }
        }

        $fail(__('trans.invalid_cname_or_alias_record'));
    }
}
