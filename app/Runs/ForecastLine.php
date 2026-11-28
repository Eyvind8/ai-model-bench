<?php

namespace App\Runs;

final readonly class ForecastLine
{
    public function __construct(
        public string $model,
        public int $requests,
        public ?int $remainingToday,
        public bool $exhausted,
    ) {}

    public function fits(): bool
    {
        return ! $this->exhausted && ($this->remainingToday === null || $this->requests <= $this->remainingToday);
    }
}
