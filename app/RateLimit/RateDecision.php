<?php

namespace App\RateLimit;

final readonly class RateDecision
{
    private function __construct(
        public bool $allowed,
        public int $waitMs = 0,
        public bool $exhausted = false,
        public ?string $reason = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function wait(int $waitMs, string $reason): self
    {
        return new self(false, max(1, $waitMs), reason: $reason);
    }

    /**
     * Daily quota is used up: do not wait, skip the model until the quota resets.
     */
    public static function exhausted(string $reason): self
    {
        return new self(false, exhausted: true, reason: $reason);
    }
}
