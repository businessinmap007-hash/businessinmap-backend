<?php

namespace App\Helpers;

class ArabicNormalizer
{
    /**
     * Normalize Arabic text for better search matching
     */
    public static function normalize(string $text): string
    {
        $text = trim($text);

        $map = [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
            'ي' => 'ي',
        ];

        return str_replace(
            array_keys($map),
            array_values($map),
            $text
        );
    }

    /**
     * Build a compact fingerprint for duplicate detection.
     *
     * Lower-cases, applies letter normalization, strips Arabic diacritics
     * (tashkeel), tatweel, punctuation and every whitespace/separator so that
     * variants like "كوكا كولا", "كوكا-كولا" and "كوكاكولا" collapse to the
     * exact same key.
     */
    public static function fingerprint(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::normalize($text);

        // Remove Arabic diacritics (harakat) and tatweel.
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        // Keep only Arabic letters, latin letters and digits; drop everything else.
        $text = preg_replace('/[^\p{Arabic}\p{L}\p{Nd}]+/u', '', (string) $text);

        return (string) $text;
    }
}
