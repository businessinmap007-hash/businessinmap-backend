<?php

namespace App\Services\OwnerListings;

/**
 * Does a message carry a phone number? The platform's fee for an owner listing is paid when the two parties swap numbers;
 * a chat that let them write the number would make the fee optional, so the chat refuses it.
 *
 * It catches what people actually do to get past such a filter: Arabic-Indic digits («٠١٠…»), spaces, dots, dashes and
 * other separators between the digits, «+20 / 0020», digits spelled in words («صفر واحد صفر…»), and the letter O for the
 * zero. A price is not caught (a million is seven digits; a number is only refused from nine digits). What it cannot catch is a number split across several messages or drawn in a picture:
 * it keeps honest people honest, it is not a wall.
 */
final class PhoneNumberGuard
{
    /** Number words (Egyptian and standard Arabic, and English) → the digit they spell. */
    private const WORDS = [
        'صفر' => 0, 'زيرو' => 0, 'zero' => 0,
        'واحد' => 1, 'واحدة' => 1, 'one' => 1,
        'اتنين' => 2, 'اثنين' => 2, 'إثنين' => 2, 'two' => 2,
        'تلاتة' => 3, 'ثلاثة' => 3, 'تلاته' => 3, 'ثلاثه' => 3, 'three' => 3,
        'اربعة' => 4, 'أربعة' => 4, 'اربعه' => 4, 'أربعه' => 4, 'four' => 4,
        'خمسة' => 5, 'خمسه' => 5, 'five' => 5,
        'ستة' => 6, 'سته' => 6, 'six' => 6,
        'سبعة' => 7, 'سبعه' => 7, 'seven' => 7,
        'تمانية' => 8, 'ثمانية' => 8, 'تمانيه' => 8, 'ثمانيه' => 8, 'تمنية' => 8, 'eight' => 8,
        'تسعة' => 9, 'تسعه' => 9, 'nine' => 9,
    ];

    /** Digits in a row from which a number is refused, whatever its shape. */
    private const LONG_RUN = 9;

    public static function contains(string $text): bool
    {
        // an Egyptian mobile is eleven digits (twelve with +20, thirteen with 0020) and a landline with its area code nine
        // or ten: every phone number clears the threshold, and a price (a million is seven digits) does not
        foreach (self::runs($text) as $run) {
            if (strlen($run) >= self::LONG_RUN) {
                return true;
            }
        }

        return false;
    }

    /**
     * The runs of digits the text spells, with the separators people put between digits taken out.
     *
     * @return list<string>
     */
    private static function runs(string $text): array
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $text = mb_strtolower($text);
        // tashkeel and tatweel hide a word from the match
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text) ?? $text;
        // the letter O standing for a zero next to digits
        $text = preg_replace('/(?<=\d)o|o(?=\d)/u', '0', $text) ?? $text;

        $runs = [];
        $current = '';

        // tokens: a letter-word, a single digit, or a separator
        preg_match_all('/\p{L}+|\d|[^\p{L}\d]/u', $text, $tokens);

        foreach ($tokens[0] as $token) {
            if (ctype_digit($token)) {
                $current .= $token;
            } elseif (isset(self::WORDS[$token])) {
                $current .= (string) self::WORDS[$token];
            } elseif (preg_match('/^[\s.\-_,\/()\\\\|·•*+:؛،]+$/u', $token) === 1 || $token === '') {
                // a separator between digits does not end the run; it is simply not part of it
                continue;
            } else {
                // a plain word ends it
                if ($current !== '') {
                    $runs[] = $current;
                }

                $current = '';
            }
        }

        if ($current !== '') {
            $runs[] = $current;
        }

        return $runs;
    }
}
