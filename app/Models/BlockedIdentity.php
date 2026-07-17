<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * A banned identity, stored as a keyed hash so it survives account anonymization.
 *
 * The whole point of this table is the ban outliving the account: see the
 * migration. Everything here is about making the membership test hard to dodge —
 * a ban that "٠١٠٠١٢٣٤٥٦٧" walks straight past is not a ban.
 */
class BlockedIdentity extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_FRAUD = 'fraud';

    protected $fillable = [
        'email_hash', 'phone_hash', 'user_id', 'reason', 'source', 'blocked_by', 'meta', 'blocked_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'blocked_at' => 'datetime',
    ];

    /**
     * ASCII digits for the Arabic-Indic ones. Real rows in this database store
     * phones as ٠١٠١٤٤١٩٧٨٨ — the app never normalized them — so a check that
     * only understands 0-9 sees an empty string for those users and bans nobody.
     */
    private const DIGIT_FOLD = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * A phone reduced to the one form the ban is keyed on.
     *
     * Folds Arabic-Indic digits, drops every separator, then collapses the
     * international spellings of an Egyptian mobile onto the local one, so
     * +20 100 123 4567, 0020100..., 20100... and 0100... are all one identity.
     *
     * Deliberately Egypt-only: 3,832 of the ~3,850 stored phones are 11-digit
     * local Egyptian numbers, and inventing per-country rules we cannot test
     * would be guessing. Any other number keeps its digits as typed — banning
     * such a number is still exact, just not format-insensitive.
     */
    public static function normalizePhone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', strtr(trim($phone), self::DIGIT_FOLD)) ?? '';

        // 00 = the international access prefix; + was already dropped as a non-digit.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Egypt: country code 20 + a 10-digit mobile starting with 1 → local 01…
        if (str_starts_with($digits, '20') && strlen($digits) === 12 && $digits[2] === '1') {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }

    /** Case and surrounding space are not identity; the rest of the address is. */
    public static function normalizeEmail(?string $email): string
    {
        return $email === null ? '' : mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Keyed so the table cannot be reversed into a contact list.
     *
     * Tied to APP_KEY: rotating the key orphans every existing row (the ban list
     * silently empties). That is a deliberate trade — a bare hash of a phone
     * number is not private at all — but it means APP_KEY rotation must be
     * treated as a data migration for this table.
     */
    private static function hash(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) Config::get('app.key'));
    }

    public static function hashEmail(?string $email): ?string
    {
        return self::hash(self::normalizeEmail($email));
    }

    public static function hashPhone(?string $phone): ?string
    {
        return self::hash(self::normalizePhone($phone));
    }

    /** Is either identity on the list? Blank inputs match nothing. */
    public static function isBlocked(?string $email, ?string $phone): bool
    {
        $emailHash = self::hashEmail($email);
        $phoneHash = self::hashPhone($phone);

        if ($emailHash === null && $phoneHash === null) {
            return false;
        }

        return self::query()
            ->when($emailHash !== null, fn ($q) => $q->orWhere('email_hash', $emailHash))
            ->when($phoneHash !== null, fn ($q) => $q->orWhere('phone_hash', $phoneHash))
            ->exists();
    }

    /**
     * Put an account's identities on the list. Call this BEFORE anonymizing —
     * afterwards the email and phone are gone and there is nothing left to hash.
     */
    public static function blockUser(User $user, ?string $reason = null, string $source = self::SOURCE_MANUAL, ?int $blockedBy = null): self
    {
        $emailHash = self::hashEmail($user->email);
        $phoneHash = self::hashPhone($user->phone);

        $existing = self::query()
            ->when($emailHash !== null, fn ($q) => $q->orWhere('email_hash', $emailHash))
            ->when($phoneHash !== null, fn ($q) => $q->orWhere('phone_hash', $phoneHash))
            ->first();

        $attributes = [
            'email_hash' => $emailHash,
            'phone_hash' => $phoneHash,
            'user_id' => (int) $user->id,
            'reason' => $reason,
            'source' => $source,
            'blocked_by' => $blockedBy,
            'blocked_at' => now(),
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        return self::create($attributes);
    }
}
