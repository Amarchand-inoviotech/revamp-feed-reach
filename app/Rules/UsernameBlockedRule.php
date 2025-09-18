<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use App\Enum\UsernameBlockedEnum;
use Illuminate\Contracts\Validation\ValidationRule;

class UsernameBlockedRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $enumValues = array_map(fn($enum) => $enum->value, UsernameBlockedEnum::cases());

        if (Str::contains(strtolower($value), $enumValues)) {
            $fail(trans('validation.username_not_allowed') . ' ' . $value . '.');
        }
    }
}
