<?php

namespace App\Support;

/**
 * The platform's handover code core: a short code a person can type — or show as a QR, which encodes the very same
 * string — that proves two parties met at a handover (a parcel picked up, a parcel delivered, an order, an arrival…).
 *
 * Format: 4 digits + 2 lower-case letters (e.g. `2954gh`). Only its hash is ever compared; whoever stores it for the
 * parties to read again keeps it encrypted. Comparison ignores case and surrounding spaces, so a scanner and a keyboard
 * land on the same value.
 *
 * Callers own the attempt counting (a wrong code must not be guessable by brute force): see
 * {@see self::MAX_ATTEMPTS}.
 */
final class HandoverCode
{
    /** Wrong tries a counterparty gets before the code must be regenerated. */
    public const MAX_ATTEMPTS = 5;

    public static function generate(): string
    {
        $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $letters = '';

        foreach (range(1, 2) as $_) {
            $letters .= chr(random_int(ord('a'), ord('z')));
        }

        return $digits.$letters;
    }

    public static function normalize(string $code): string
    {
        return mb_strtolower(trim($code));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    public static function matches(?string $storedHash, string $code): bool
    {
        return $storedHash !== null && $storedHash !== '' && hash_equals($storedHash, self::hash($code));
    }
}
