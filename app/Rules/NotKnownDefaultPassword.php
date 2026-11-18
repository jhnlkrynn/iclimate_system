<?php

namespace App\Rules;

use App\Support\DefaultPasswords;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A new password may not be one of the shared default passwords. */
class NotKnownDefaultPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DefaultPasswords::isKnown($value)) {
            $fail('Choose your own password. This one is shared and not safe.');
        }
    }
}
