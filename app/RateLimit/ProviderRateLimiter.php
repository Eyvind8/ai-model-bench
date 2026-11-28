<?php

namespace App\RateLimit;

use App\Contracts\RateLimiterInterface;
use App\Llm\ProviderConfig;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;

/**
 * Fixed-window counters in cache: RPM/TPM per minute, RPD per UTC day (per provider+model),
 * plus a minimal pause between any two requests to the same provider.
 *
 * Designed for a single queue worker; counters are not locked.
 */
final class ProviderRateLimiter implements RateLimiterInterface
{
    private const PREFIX = 'bench:rl';

    private const MINUTE = 60;

    public function __construct(private readonly Repository $cache) {}

    public function acquire(ProviderConfig $provider, string $modelId, int $estimatedTokens): RateDecision
    {
        $limits = $provider->limitsFor($modelId);
        $decision = $this->check($provider, $modelId, $limits, $estimatedTokens);

        if ($decision->allowed) {
            $this->record($provider, $modelId, $estimatedTokens);
        }

        return $decision;
    }

    public function markExhausted(ProviderConfig $provider, string $modelId): void
    {
        $this->cache->put($this->key($provider, $modelId, 'exhausted'), true, $this->secondsUntilDayEnd());
    }

    public function remaining(ProviderConfig $provider, string $modelId): RemainingQuota
    {
        $limits = $provider->limitsFor($modelId);

        return new RemainingQuota(
            rpm: $this->left($limits['rpm'] ?? null, $this->minuteKey($provider, $modelId, 'rpm')),
            rpd: $this->left($limits['rpd'] ?? null, $this->dayKey($provider, $modelId)),
            tpm: $this->left($limits['tpm'] ?? null, $this->minuteKey($provider, $modelId, 'tpm')),
            exhausted: $this->isExhausted($provider, $modelId, $limits),
        );
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    private function check(ProviderConfig $provider, string $modelId, array $limits, int $tokens): RateDecision
    {
        if ($this->isExhausted($provider, $modelId, $limits)) {
            return RateDecision::exhausted("daily limit reached (rpd {$this->limitLabel($limits['rpd'] ?? null)})");
        }

        if ($this->over($limits['rpm'] ?? null, $this->minuteKey($provider, $modelId, 'rpm'), 1)) {
            return RateDecision::wait($this->msUntilMinuteEnd(), "rpm {$limits['rpm']} reached");
        }

        $usedTokens = (int) $this->cache->get($this->minuteKey($provider, $modelId, 'tpm'), 0);
        if (isset($limits['tpm']) && $usedTokens > 0 && $usedTokens + $tokens > $limits['tpm']) {
            return RateDecision::wait($this->msUntilMinuteEnd(), "tpm {$limits['tpm']} reached");
        }

        $intervalWait = $this->intervalWaitMs($provider, (int) ($limits['min_interval_ms'] ?? 0));

        return $intervalWait > 0 ? RateDecision::wait($intervalWait, 'min interval') : RateDecision::allow();
    }

    private function record(ProviderConfig $provider, string $modelId, int $tokens): void
    {
        $this->increment($this->minuteKey($provider, $modelId, 'rpm'), 1, self::MINUTE);
        $this->increment($this->minuteKey($provider, $modelId, 'tpm'), $tokens, self::MINUTE);
        $this->increment($this->dayKey($provider, $modelId), 1, $this->secondsUntilDayEnd());
        $this->cache->put($this->providerKey($provider, 'last'), $this->nowMs(), self::MINUTE * 10);
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    private function isExhausted(ProviderConfig $provider, string $modelId, array $limits): bool
    {
        return $this->cache->has($this->key($provider, $modelId, 'exhausted'))
            || $this->over($limits['rpd'] ?? null, $this->dayKey($provider, $modelId), 1);
    }

    private function over(?int $limit, string $key, int $amount): bool
    {
        return $limit !== null && (int) $this->cache->get($key, 0) + $amount > $limit;
    }

    private function left(?int $limit, string $key): ?int
    {
        return $limit === null ? null : max(0, $limit - (int) $this->cache->get($key, 0));
    }

    private function intervalWaitMs(ProviderConfig $provider, int $intervalMs): int
    {
        $last = $this->cache->get($this->providerKey($provider, 'last'));

        return $intervalMs > 0 && $last !== null ? max(0, (int) $last + $intervalMs - $this->nowMs()) : 0;
    }

    private function increment(string $key, int $amount, int $ttl): void
    {
        $this->cache->put($key, (int) $this->cache->get($key, 0) + $amount, $ttl);
    }

    private function minuteKey(ProviderConfig $provider, string $modelId, string $metric): string
    {
        return $this->key($provider, $modelId, $metric.':'.intdiv($this->now()->getTimestamp(), self::MINUTE));
    }

    private function dayKey(ProviderConfig $provider, string $modelId): string
    {
        return $this->key($provider, $modelId, 'rpd:'.$this->now()->format('Y-m-d'));
    }

    private function key(ProviderConfig $provider, string $modelId, string $suffix): string
    {
        return self::PREFIX.':'.$provider->code.':'.md5($modelId).':'.$suffix;
    }

    private function providerKey(ProviderConfig $provider, string $suffix): string
    {
        return self::PREFIX.':'.$provider->code.':'.$suffix;
    }

    private function msUntilMinuteEnd(): int
    {
        return (self::MINUTE - $this->now()->getTimestamp() % self::MINUTE) * 1000 - (int) $this->now()->format('v');
    }

    private function secondsUntilDayEnd(): int
    {
        return max(1, (int) $this->now()->diffInSeconds($this->now()->endOfDay(), true) + 1);
    }

    private function limitLabel(?int $limit): string
    {
        return $limit === null ? 'provider' : (string) $limit;
    }

    private function now(): Carbon
    {
        return Carbon::now('UTC');
    }

    private function nowMs(): int
    {
        return (int) $this->now()->getTimestampMs();
    }
}
