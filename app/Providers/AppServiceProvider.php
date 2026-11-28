<?php

namespace App\Providers;

use App\Articles\DnsHostResolver;
use App\Articles\HttpArticleFetcher;
use App\Articles\ReadabilityExtractor;
use App\Contracts\ArticleExtractorInterface;
use App\Contracts\ArticleFetcherInterface;
use App\Contracts\HostResolverInterface;
use App\Contracts\RateLimiterInterface;
use App\RateLimit\ProviderRateLimiter;
use App\RateLimit\RetryPolicy;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RateLimiterInterface::class, ProviderRateLimiter::class);
        $this->app->bind(ArticleFetcherInterface::class, HttpArticleFetcher::class);
        $this->app->bind(ArticleExtractorInterface::class, ReadabilityExtractor::class);
        $this->app->bind(HostResolverInterface::class, DnsHostResolver::class);

        $this->app->singleton(RetryPolicy::class, fn ($app) => $this->retryPolicy($app->make(Repository::class)));
    }

    public function boot(): void {}

    private function retryPolicy(Repository $config): RetryPolicy
    {
        return new RetryPolicy(
            maxRetries: (int) $config->get('bench.retry.max', 3),
            baseDelayMs: (int) $config->get('bench.retry.base_delay_ms', 2000),
            maxDelayMs: (int) $config->get('bench.retry.max_delay_ms', 300_000),
            jitterMs: (int) $config->get('bench.retry.jitter_ms', 1000),
        );
    }
}
