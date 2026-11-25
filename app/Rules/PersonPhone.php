<?php

namespace App\Rules;

use App\Services\MembershipSales\ContactNotePhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ContactNotePhone::isValidPersonPhone($value)) {
            $fail(__('validation.regex', ['attribute' => $attribute]));
        }
    }
}
