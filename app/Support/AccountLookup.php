<?php

namespace App\Support;

use App\Models\User;

/**
 * A customer who walks in without the app may still HAVE an account: the business writes his phone (or e-mail) and the
 * booking is recorded under that account — so it counts, can be rated and shows his history.
 */
final class AccountLookup
{
    public static function find(?string $phone, ?string $email, int $exceptId): ?User
    {
        $email = $email !== null ? strtolower(trim($email)) : '';

        if ($email !== '') {
            $user = self::usable()->where('email', $email)->where('id', '!=', $exceptId)->first();

            if ($user) {
                return $user;
            }
        }

        $variants = self::phoneVariants((string) $phone);

        if ($variants === []) {
            return null;
        }

        return self::usable()->whereIn('phone', $variants)->where('id', '!=', $exceptId)->orderBy('id')->first();
    }

    /** «01012345678», «+201012345678» and «201012345678» are one number. */
    public static function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 8) {
            return [];
        }

        $local = $digits;

        if (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $local = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $local = '0' . $digits;
        }

        $international = str_starts_with($local, '0') && strlen($local) === 11 ? '20' . substr($local, 1) : null;

        return array_values(array_unique(array_filter([$digits, $local, $international, $international ? '+' . $international : null])));
    }

    private static function usable()
    {
        return User::query()->whereNull('anonymized_at')->whereNull('banned_at');
    }
}
