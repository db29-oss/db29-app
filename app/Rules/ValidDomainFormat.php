<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Pdp\Domain;
use Pdp\SyntaxError;
use Pdp\TopLevelDomains;

class ValidDomainFormat implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tlds_alpha_by_domain_path = storage_path('app/public/').'tlds-alpha-by-domain.txt';

        $top_level_domains = TopLevelDomains::fromPath($tlds_alpha_by_domain_path);

        try {
            $top_level_domains->resolve(request('domain'));
        } catch (SyntaxError) {
            $fail(__('trans.invalid_domain_name'));
        }
    }
}
