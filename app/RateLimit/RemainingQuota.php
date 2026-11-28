<?php

namespace App\RateLimit;

/**
 * Remaining requests in the current windows; null = no limit configured.
 */
final readonly class RemainingQuota
{
    public function __construct(
        public ?int $rpm,
        public ?int $rpd,
        public ?int $tpm,
        public bool $exhausted,
    ) {}
}
