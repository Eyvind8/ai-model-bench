<?php

namespace App\Contracts;

use App\Llm\ProviderConfig;
use App\RateLimit\RateDecision;
use App\RateLimit\RemainingQuota;

interface RateLimiterInterface
{
    /**
     * Checks all limits and, when allowed, records the request (quota is consumed).
     */
    public function acquire(ProviderConfig $provider, string $modelId, int $estimatedTokens): RateDecision;

    /**
     * The provider says the quota is gone (e.g. repeated 429): skip the model until the daily reset.
     */
    public function markExhausted(ProviderConfig $provider, string $modelId): void;

    public function remaining(ProviderConfig $provider, string $modelId): RemainingQuota;
}
