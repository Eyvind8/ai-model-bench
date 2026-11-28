<?php

namespace App\Articles;

/**
 * Collapses whitespace and truncates long texts at a word boundary (TPM of free tiers is small).
 */
final class ArticleTextNormalizer
{
    public function normalize(string $text, int $maxChars): NormalizedText
    {
        $lines = array_map(fn (string $line) => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)), explode("\n", str_replace("\r", '', $text)));
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
        $length = mb_strlen($text);

        if ($maxChars <= 0 || $length <= $maxChars) {
            return new NormalizedText($text, $length, false);
        }

        $cut = mb_substr($text, 0, $maxChars);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $maxChars * 0.8) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return new NormalizedText(rtrim($cut).'…', $length, true);
    }
}
