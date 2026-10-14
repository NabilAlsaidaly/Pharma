<?php

namespace App\Domains\ManagementIdentity\Application;

use Closure;
use Illuminate\Validation\Rules\Password;

class OwnerCredentials
{
    public static function email(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    public static function name(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? $value;
    }

    public static function nameRules(): array
    {
        return ['required', 'string', 'max:150', 'regex:/[\p{L}\p{N}]/u', 'not_regex:/[\p{Cc}\p{Cf}<>]/u'];
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', Password::min(12), function (string $attribute, mixed $value, Closure $fail): void {
            // The configured Laravel bcrypt hasher must never silently truncate credentials.
            if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                $fail('كلمة المرور تتجاوز الحد الآمن أو تحتوي محارف غير صالحة.');
            }
        }];
    }
}
