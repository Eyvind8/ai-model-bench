<?php

namespace App\RateLimit;

use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use App\Llm\Exceptions\UnknownDriverException;

/**
 * Which provider errors are transient and how long to back off before the next attempt.
 */
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxRetries = 3,
        private int $baseDelayMs = 2000,
        private int $maxDelayMs = 300_000,
        private int $jitterMs = 1000,
    ) {}

    /**
     * @param  int  $retryNo  1-based number of the retry about to happen
     */
    public function shouldRetry(ProviderException $error, int $retryNo): bool
    {
        return $retryNo <= $this->maxRetries && $this->isTransient($error);
    }

    public function delayMs(ProviderException $error, int $retryNo): int
    {
        if ($error instanceof RateLimitedException && $error->retryAfterSeconds !== null) {
            return $error->retryAfterSeconds * 1000;
        }

        $backoff = min($this->maxDelayMs, $this->baseDelayMs * 2 ** max(0, $retryNo - 1));

        return (int) min($this->maxDelayMs, $backoff + ($this->jitterMs > 0 ? random_int(0, $this->jitterMs) : 0));
    }

    private function isTransient(ProviderException $error): bool
    {
        if ($error instanceof UnknownDriverException) {
            return false;
        }

        return $error instanceof RateLimitedException
            || $error->httpStatus === null
            || $error->httpStatus >= 500
            || $error->httpStatus === 408;
    }
}
