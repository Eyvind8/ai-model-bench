<?php

namespace App\Articles;

/**
 * Cheap script-based detector for uk / ru / en. Good enough to compare
 * "announcement language == article language", not a general-purpose detector.
 */
final class LanguageDetector
{
    private const MIN_LETTERS = 20;

    public function detect(string $text): ?string
    {
        $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text);
        $latin = preg_match_all('/[a-zA-Z]/', $text);

        if ($cyrillic + $latin < self::MIN_LETTERS) {
            return null;
        }
        if ($latin > $cyrillic) {
            return 'en';
        }

        $uk = preg_match_all('/[іїєґІЇЄҐ]/u', $text);
        $ru = preg_match_all('/[ыэъёЫЭЪЁ]/u', $text);

        return match (true) {
            $uk > $ru => 'uk',
            $ru > $uk => 'ru',
            default => null,
        };
    }
}
