<?php

namespace App\Articles;

use App\Contracts\ArticleExtractorInterface;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;

/**
 * Adapter over fivefilters/readability.php. The parser is stateful, so a new instance
 * is created per extraction (adapter is the only place that knows the library).
 */
final class ReadabilityExtractor implements ArticleExtractorInterface
{
    private const BLOCK_END = '#</(p|div|h[1-6]|li|blockquote|pre|tr|section|article|figure)>|<br\s*/?>#i';

    public function extract(string $html, string $url): ExtractedArticle
    {
        $readability = new Readability(new Configuration([
            'OriginalURL' => $url,
            'FixRelativeURLs' => true,
            'SummonCthulhu' => true,
        ]));

        try {
            $readability->parse($html);
        } catch (ParseException $e) {
            throw new ArticleFetchException('Could not extract article text: '.$e->getMessage(), previous: $e);
        }

        return new ExtractedArticle(
            title: $this->clean((string) $readability->getTitle()) ?: null,
            text: $this->htmlToText((string) $readability->getContent()),
            author: $this->clean((string) $readability->getAuthor()) ?: null,
            publishedAt: $this->meta($html, 'article:published_time'),
            lang: $this->lang($html),
        );
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace(self::BLOCK_END, "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function meta(string $html, string $property): ?string
    {
        $pattern = '#<meta[^>]+(?:property|name)=["\']'.preg_quote($property, '#').'["\'][^>]+content=["\']([^"\']+)["\']#i';

        return preg_match($pattern, $html, $m) === 1 ? trim($m[1]) : null;
    }

    private function lang(string $html): ?string
    {
        return preg_match('#<html[^>]*\slang=["\']?([a-zA-Z]{2})#i', $html, $m) === 1 ? strtolower($m[1]) : null;
    }

    private function clean(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
