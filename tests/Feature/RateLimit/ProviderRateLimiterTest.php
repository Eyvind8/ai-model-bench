<?php

namespace Tests\Feature\RateLimit;

use App\Llm\ProviderConfig;
use App\RateLimit\ProviderRateLimiter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProviderRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00', 'UTC'));
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    private function provider(array $limits, string $code = 'groq'): ProviderConfig
    {
        return new ProviderConfig($code, $code, 'openai', 'https://x.test', 'k', limits: $limits);
    }

    private function limiter(): ProviderRateLimiter
    {
        return $this->app->make(ProviderRateLimiter::class);
    }

    private function travelSeconds(float $seconds): void
    {
        $this->travelTo(now()->addMilliseconds((int) ($seconds * 1000)));
    }

    public function test_no_limits_always_allowed(): void
    {
        $provider = $this->provider([]);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($this->limiter()->acquire($provider, 'm', 100)->allowed);
        }
    }

    public function test_rpm_limit_asks_to_wait_until_window_resets(): void
    {
        $provider = $this->provider(['rpm' => 2]);

        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);
        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);

        $decision = $this->limiter()->acquire($provider, 'm', 1);
        $this->assertFalse($decision->allowed);
        $this->assertFalse($decision->exhausted);
        $this->assertGreaterThan(0, $decision->waitMs);
        $this->assertLessThanOrEqual(60_000, $decision->waitMs);
        $this->assertStringContainsString('rpm', (string) $decision->reason);

        $this->travelSeconds(61);
        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);
    }

    public function test_limits_are_per_model(): void
    {
        $provider = $this->provider(['rpm' => 1]);

        $this->assertTrue($this->limiter()->acquire($provider, 'a', 1)->allowed);
        $this->assertTrue($this->limiter()->acquire($provider, 'b', 1)->allowed);
        $this->assertFalse($this->limiter()->acquire($provider, 'a', 1)->allowed);
    }

    public function test_rpd_exhausts_model_until_utc_midnight(): void
    {
        $provider = $this->provider(['rpd' => 1]);

        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);

        $decision = $this->limiter()->acquire($provider, 'm', 1);
        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->exhausted);
        $this->assertStringContainsString('rpd', (string) $decision->reason);

        $this->travelTo(CarbonImmutable::parse('2026-11-29 00:00:01', 'UTC'));
        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);
    }

    public function test_tpm_counts_tokens(): void
    {
        $provider = $this->provider(['tpm' => 1000]);

        $this->assertTrue($this->limiter()->acquire($provider, 'm', 600)->allowed);

        $decision = $this->limiter()->acquire($provider, 'm', 600);
        $this->assertFalse($decision->allowed);
        $this->assertStringContainsString('tpm', (string) $decision->reason);

        $this->assertTrue($this->limiter()->acquire($provider, 'm', 300)->allowed);
    }

    public function test_request_larger_than_tpm_is_allowed_on_empty_window(): void
    {
        $provider = $this->provider(['tpm' => 1000]);

        $this->assertTrue($this->limiter()->acquire($provider, 'm', 5000)->allowed);
    }

    public function test_min_interval_is_per_provider(): void
    {
        $provider = $this->provider(['min_interval_ms' => 3000]);

        $this->assertTrue($this->limiter()->acquire($provider, 'a', 1)->allowed);

        $decision = $this->limiter()->acquire($provider, 'b', 1);
        $this->assertFalse($decision->allowed);
        $this->assertSame(3000, $decision->waitMs);

        $this->assertTrue($this->limiter()->acquire($this->provider(['min_interval_ms' => 3000], 'gemini'), 'a', 1)->allowed);

        $this->travelSeconds(1);
        $this->assertSame(2000, $this->limiter()->acquire($provider, 'b', 1)->waitMs);

        $this->travelSeconds(2);
        $this->assertTrue($this->limiter()->acquire($provider, 'b', 1)->allowed);
    }

    public function test_denied_acquire_does_not_consume_quota(): void
    {
        $provider = $this->provider(['rpm' => 2, 'min_interval_ms' => 1000]);

        $this->limiter()->acquire($provider, 'm', 1);
        $this->limiter()->acquire($provider, 'm', 1);
        $this->limiter()->acquire($provider, 'm', 1);

        $this->travelSeconds(1);
        $this->assertTrue($this->limiter()->acquire($provider, 'm', 1)->allowed);
    }

    public function test_mark_exhausted(): void
    {
        $provider = $this->provider([]);

        $this->limiter()->markExhausted($provider, 'm');

        $decision = $this->limiter()->acquire($provider, 'm', 1);
        $this->assertTrue($decision->exhausted);
        $this->assertTrue($this->limiter()->acquire($provider, 'other', 1)->allowed);
    }

    public function test_remaining(): void
    {
        $provider = $this->provider(['rpm' => 10, 'rpd' => 100]);

        $this->limiter()->acquire($provider, 'm', 1);
        $this->limiter()->acquire($provider, 'm', 1);

        $remaining = $this->limiter()->remaining($provider, 'm');

        $this->assertSame(98, $remaining->rpd);
        $this->assertSame(8, $remaining->rpm);
        $this->assertFalse($remaining->exhausted);
        $this->assertNull($this->limiter()->remaining($this->provider([]), 'm')->rpd);
    }
}
