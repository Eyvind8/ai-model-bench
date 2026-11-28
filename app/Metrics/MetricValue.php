<?php

namespace App\Metrics;

final readonly class MetricValue
{
    public function __construct(
        public float $value,
        public ?string $details = null,
    ) {}
}
