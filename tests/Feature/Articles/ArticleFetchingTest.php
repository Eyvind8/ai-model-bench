<?php

namespace Tests\Feature\Articles;

use App\Articles\ArticleFetchException;
use App\Articles\HttpArticleFetcher;
use App\Articles\ReadabilityExtractor;
use App\Articles\UrlGuard;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleFetchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns();
    }

    private function fetcher(): HttpArticleFetcher
    {
        return $this->app->make(HttpArticleFetcher::class);
    }

    /**
     * @return array<string, string>
     */
    private function html(): array
    {
        return ['Content-Type' => 'text/html; charset=utf-8'];
    }

    #[DataProvider('blockedUrls')]
    public function test_guard_blocks(string $url): void
    {
        $this->expectException(ArticleFetchException::class);

        $this->app->make(UrlGuard::class)->assertAllowed($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'],
            'ftp' => ['ftp://news.test/a'],
            'loopback ip' => ['http://127.0.0.1/admin'],
            'private ip' => ['http://192.168.1.10/'],
            'metadata ip' => ['http://169.254.169.254/latest/meta-data'],
            'ipv6 loopback' => ['http://[::1]/'],
            'host resolving to private' => ['https://db.internal.test/'],
        ];
    }

    public function test_guard_allows_public_host(): void
    {
        $this->app->make(UrlGuard::class)->assertAllowed('https://news.test/a');
        $this->addToAssertionCount(1);
    }

    public function test_fetches_html(): void
    {
        Http::fake(['news.test/*' => Http::response($this->fixture('articles/article.html'), 200, $this->html())]);

        $page = $this->fetcher()->fetch('https://news.test/nbu');

        $this->assertSame('https://news.test/nbu', $page->url);
        $this->assertStringContainsString('НБУ знизив', $page->html);
    }

    public function test_follows_relative_redirect(): void
    {
        Http::fake([
            'news.test/old' => Http::response('', 301, ['Location' => '/new']),
            'news.test/new' => Http::response('<html><body>ok</body></html>', 200, $this->html()),
        ]);

        $this->assertSame('https://news.test/new', $this->fetcher()->fetch('https://news.test/old')->url);
    }

    public function test_redirect_to_private_host_is_blocked(): void
    {
        Http::fake(['news.test/*' => Http::response('', 302, ['Location' => 'http://db.internal.test/secret'])]);

        $this->expectException(ArticleFetchException::class);
        $this->expectExceptionMessage('Private');

        $this->fetcher()->fetch('https://news.test/go');
    }

    public function test_redirect_loop_is_limited(): void
    {
        Http::fake(['news.test/*' => Http::response('', 302, ['Location' => 'https://news.test/loop'])]);

        $this->expectExceptionMessage('Too many redirects');

        $this->fetcher()->fetch('https://news.test/loop');
    }

    public function test_http_error(): void
    {
        Http::fake(['*' => Http::response('nope', 404, $this->html())]);

        $this->expectExceptionMessage('HTTP 404');

        $this->fetcher()->fetch('https://news.test/missing');
    }

    public function test_non_html_is_rejected(): void
    {
        Http::fake(['*' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf'])]);

        $this->expectExceptionMessage('Not an HTML page');

        $this->fetcher()->fetch('https://news.test/file.pdf');
    }

    public function test_size_limit(): void
    {
        config(['bench.articles.max_bytes' => 1024 * 1024]);
        Http::fake(['*' => Http::response(str_repeat('a', 1024 * 1024 + 10), 200, $this->html())]);

        $this->expectExceptionMessage('larger than 1 MB');

        $this->fetcher()->fetch('https://news.test/huge');
    }

    public function test_non_utf8_charset_is_converted(): void
    {
        $html = mb_convert_encoding('<html><body>Привіт</body></html>', 'Windows-1251', 'UTF-8');
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=windows-1251'])]);

        $this->assertStringContainsString('Привіт', $this->fetcher()->fetch('https://news.test/cp')->html);
    }

    public function test_readability_extracts_article(): void
    {
        $article = $this->app->make(ReadabilityExtractor::class)
            ->extract($this->fixture('articles/article.html'), 'https://news.test/nbu');

        $this->assertStringContainsString('НБУ знизив облікову ставку', (string) $article->title);
        $this->assertStringContainsString('Національний банк України ухвалив рішення', $article->text);
        $this->assertStringContainsString('Наступне засідання правління', $article->text);
        $this->assertStringNotContainsString('Реклама', $article->text);
        $this->assertStringNotContainsString('window.ads', $article->text);
        $this->assertSame('uk', $article->lang);
        $this->assertSame('2026-11-27T10:00:00+02:00', $article->publishedAt);
    }
}
