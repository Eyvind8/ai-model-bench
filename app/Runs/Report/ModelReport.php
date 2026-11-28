<?php

namespace App\Runs\Report;

final readonly class ModelReport
{
    /**
     * @param  array<string, int>  $statuses  result status => count
     * @param  array<string, float|null>  $metrics  auto metric label => mean
     */
    public function __construct(
        public string $model,
        public int $total,
        public array $statuses,
        public ?float $jsonValidRate,
        public ?float $schemaValidRate,
        public ?float $latencyP50,
        public ?float $latencyP95,
        public ?float $avgCompletionTokens,
        public array $metrics,
    ) {}

    public function errorRate(): ?float
    {
        $done = $this->total - ($this->statuses['pending'] ?? 0);

        return $done === 0 ? null : (($this->statuses['error'] ?? 0) + ($this->statuses['rate_limited'] ?? 0)) / $done;
    }
}
