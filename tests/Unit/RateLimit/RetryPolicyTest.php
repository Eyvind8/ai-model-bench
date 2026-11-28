<?php

namespace Tests\Unit\RateLimit;

use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use App\RateLimit\RetryPolicy;
use PHPUnit\Framework\TestCase;

class RetryPolicyTest extends TestCase
{
    private function policy(): RetryPolicy
    {
        return new RetryPolicy(maxRetries: 3, baseDelayMs: 2000, maxDelayMs: 60_000, jitterMs: 0);
    }

    public function test_retryable_errors(): void
    {
        $policy = $this->policy();

        $this->assertTrue($policy->shouldRetry(new RateLimitedException('429'), 1));
        $this->assertTrue($policy->shouldRetry(new ProviderException('502', 502), 1));
        $this->assertTrue($policy->shouldRetry(new ProviderException('timeout'), 1));
        $this->assertFalse($policy->shouldRetry(new ProviderException('bad request', 400), 1));
        $this->assertFalse($policy->shouldRetry(new ProviderException('no key', 401), 1));
    }

    public function test_stops_after_max_retries(): void
    {
        $this->assertTrue($this->policy()->shouldRetry(new RateLimitedException('429'), 3));
        $this->assertFalse($this->policy()->shouldRetry(new RateLimitedException('429'), 4));
    }

    public function test_exponential_backoff_capped(): void
    {
        $policy = $this->policy();
        $error = new ProviderException('502', 502);

        $this->assertSame(2000, $policy->delayMs($error, 1));
        $this->assertSame(4000, $policy->delayMs($error, 2));
        $this->assertSame(8000, $policy->delayMs($error, 3));
        $this->assertSame(60_000, $policy->delayMs($error, 10));
    }

    public function test_retry_after_wins(): void
    {
        $this->assertSame(31_000, $this->policy()->delayMs(new RateLimitedException('429', retryAfterSeconds: 31), 1));
    }

    public function test_jitter_is_added(): void
    {
        $policy = new RetryPolicy(maxRetries: 3, baseDelayMs: 1000, maxDelayMs: 60_000, jitterMs: 500);

        $delay = $policy->delayMs(new ProviderException('502', 502), 1);

        $this->assertGreaterThanOrEqual(1000, $delay);
        $this->assertLessThanOrEqual(1500, $delay);
    }
}
