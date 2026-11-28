<?php

namespace App\Metrics;

use App\Models\DatasetItem;

final readonly class MetricContext
{
    /**
     * @param  array<string, mixed>|null  $parsed  parsed JSON answer
     */
    public function __construct(
        public ?string $content,
        public ?array $parsed,
        public ?DatasetItem $item,
    ) {}

    public function field(string $name): mixed
    {
        return $this->parsed[$name] ?? null;
    }
}
