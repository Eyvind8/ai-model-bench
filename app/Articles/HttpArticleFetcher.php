<?php

namespace App\Articles;

use App\Contracts\ArticleFetcherInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * Downloads an HTML page: redirects are followed manually so every hop passes UrlGuard,
 * body size is capped, only HTML is accepted.
 */
final class HttpArticleFetcher implements ArticleFetcherInterface
{
    private const CHUNK = 65536;

    public function __construct(
        private readonly Factory $http,
        private readonly UrlGuard $guard,
        private readonly Repository $config,
    ) {}

    public function fetch(string $url): FetchedPage
    {
        $maxRedirects = (int) $this->config->get('bench.articles.max_redirects', 5);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $this->guard->assertAllowed($url);
            $response = $this->request($url);

            if (! $response->redirect()) {
                return new FetchedPage($url, $this->html($response));
            }

            $url = $this->resolveLocation($url, (string) $response->header('Location'));
        }

        throw new ArticleFetchException("Too many redirects (> {$maxRedirects})");
    }

    private function request(string $url): Response
    {
        try {
            return $this->http
                ->withoutRedirecting()
                ->withUserAgent((string) $this->config->get('bench.articles.user_agent'))
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->timeout((int) $this->config->get('bench.articles.timeout', 15))
                ->withOptions(['stream' => true])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new ArticleFetchException('Connection failed: '.$e->getMessage(), previous: $e);
        }
    }

    private function html(Response $response): string
    {
        if ($response->failed()) {
            throw new ArticleFetchException("HTTP {$response->status()}");
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'html')) {
            throw new ArticleFetchException('Not an HTML page: '.($contentType ?: 'unknown content type'));
        }

        return $this->toUtf8($this->readLimited($response), $contentType);
    }

    private function readLimited(Response $response): string
    {
        $max = (int) $this->config->get('bench.articles.max_bytes', 5 * 1024 * 1024);
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(self::CHUNK);
            if (strlen($body) > $max) {
                throw new ArticleFetchException(sprintf('Page is larger than %d MB', intdiv($max, 1024 * 1024)));
            }
        }

        return $body;
    }

    private function toUtf8(string $body, string $contentType): string
    {
        if (preg_match('/charset=([\w-]+)/', $contentType, $m) !== 1 || in_array(strtolower($m[1]), ['utf-8', 'utf8'], true)) {
            return $body;
        }

        $charset = strtoupper($m[1]);

        return in_array($charset, array_map('strtoupper', mb_list_encodings()), true)
            ? (string) mb_convert_encoding($body, 'UTF-8', $charset)
            : $body;
    }

    private function resolveLocation(string $base, string $location): string
    {
        if ($location === '') {
            throw new ArticleFetchException('Redirect without Location header');
        }
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }
}
