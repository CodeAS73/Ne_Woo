<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

class AccountPassword
{
    public static function rules(): array
    {
        return ['required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->numbers(), function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('The password must fit within 72 UTF-8 bytes.');
            }
        }];
    }
}
