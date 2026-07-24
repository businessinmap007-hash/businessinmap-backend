<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The single source of truth for the account-password policy: 8–20 characters,
 * and must mix an upper-case letter, a lower-case letter and a digit. Every
 * user-facing password WRITE (web signup, API register, password reset, profile
 * change) validates through here so the rule can never drift between entry
 * points again — it used to be `required` with no minimum on the web, `min:6`
 * on the API, and `min:6` on reset.
 */
final class PasswordPolicy
{
    public const MIN = 8;
    public const MAX = 20;

    /**
     * Validation rules for setting a new password. `confirmed` is opt-in: not
     * every form ships a confirmation field (the legacy web signup form does
     * not), so callers that have one pass true.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $confirmed = true): array
    {
        $rules = [
            'required',
            'string',
            'max:' . self::MAX,
            Password::min(self::MIN)->mixedCase()->numbers(),
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
