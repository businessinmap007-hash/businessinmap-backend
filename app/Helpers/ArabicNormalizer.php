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
}
