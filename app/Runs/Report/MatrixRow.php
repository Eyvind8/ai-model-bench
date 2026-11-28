<?php

namespace App\Runs\Report;

use App\Models\DatasetItem;
use App\Models\Result;
use App\Support\Stats;

final readonly class MatrixRow
{
    /**
     * @param  array<int, list<Result>>  $cells  model id => results (attempts)
     */
    public function __construct(
        public ?DatasetItem $item,
        public string $label,
        public string $input,
        public ?int $expectedScore,
        public array $cells,
    ) {}

    /**
     * @return list<Result>
     */
    public function results(int $modelId): array
    {
        return $this->cells[$modelId] ?? [];
    }

    public function meanScore(int $modelId, ?string $field): ?float
    {
        if ($field === null) {
            return null;
        }

        $scores = array_values(array_filter(
            array_map(fn (Result $r) => $r->parsed_json[$field] ?? null, $this->results($modelId)),
            'is_numeric',
        ));

        return Stats::mean(array_map('floatval', $scores));
    }
}
