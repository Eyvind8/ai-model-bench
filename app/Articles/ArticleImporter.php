<?php

namespace App\Articles;

use App\Contracts\ArticleExtractorInterface;
use App\Contracts\ArticleFetcherInterface;
use App\Enums\FetchStatus;
use App\Jobs\FetchArticleJob;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;

/**
 * Turns URLs into dataset items: queues them, then downloads, extracts and normalizes the text.
 */
final class ArticleImporter
{
    public function __construct(
        private readonly ArticleFetcherInterface $fetcher,
        private readonly ArticleExtractorInterface $extractor,
        private readonly ArticleTextNormalizer $normalizer,
        private readonly LanguageDetector $languages,
        private readonly Dispatcher $bus,
        private readonly Repository $config,
    ) {}

    /**
     * Creates a pending item and queues the download. Null when the URL is already in the dataset.
     *
     * @throws ArticleFetchException invalid URL
     */
    public function queue(Dataset $dataset, string $url, ?int $expectedScore = null, ?string $title = null): ?DatasetItem
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ArticleFetchException("Invalid URL: {$url}");
        }
        if ($dataset->items()->where('source_url', $url)->exists()) {
            return null;
        }

        $item = $dataset->items()->create([
            'source_url' => $url,
            'title' => $title,
            'expected_score' => $expectedScore,
            'fetch_status' => FetchStatus::Pending,
        ]);
        $this->bus->dispatch(new FetchArticleJob($item->id));

        return $item;
    }

    /**
     * Re-queues items; without $download the stored HTML is re-extracted when available.
     */
    public function requeue(DatasetItem $item, bool $download): void
    {
        $item->update(['fetch_status' => FetchStatus::Pending, 'fetch_error' => null]);
        $this->bus->dispatch(new FetchArticleJob($item->id, $download));
    }

    public function process(DatasetItem $item, bool $download = true): void
    {
        try {
            $html = $download || $item->source_html === null ? $this->download($item) : $item->source_html;
            $this->fill($item, $this->extractor->extract($html, (string) $item->source_url));
        } catch (ArticleFetchException $e) {
            $item->update(['fetch_status' => FetchStatus::Failed, 'fetch_error' => $e->getMessage()]);
        }
    }

    private function download(DatasetItem $item): string
    {
        $page = $this->fetcher->fetch((string) $item->source_url);
        $item->update(['source_html' => $page->html]);

        return $page->html;
    }

    private function fill(DatasetItem $item, ExtractedArticle $article): void
    {
        $normalized = $this->normalizer->normalize($article->text, (int) $this->config->get('bench.articles.max_chars', 12000));
        $minChars = (int) $this->config->get('bench.articles.min_chars', 500);

        if ($normalized->originalChars < $minChars) {
            throw new ArticleFetchException(sprintf(
                'Text too short (%d chars, min %d): paywall, JS rendering or empty page. Fill the text manually.',
                $normalized->originalChars,
                $minChars,
            ));
        }

        $item->update([
            'title' => $item->title ?? $article->title,
            'input' => $normalized->text,
            'meta_json' => array_filter([
                'site' => parse_url((string) $item->source_url, PHP_URL_HOST),
                'author' => $article->author,
                'published_at' => $article->publishedAt,
                'lang' => $article->lang ?? $this->languages->detect($normalized->text),
                'chars' => $normalized->originalChars,
                'truncated' => $normalized->truncated ?: null,
            ], fn ($v) => $v !== null),
            'fetch_status' => FetchStatus::Ok,
            'fetch_error' => null,
        ]);
    }
}
