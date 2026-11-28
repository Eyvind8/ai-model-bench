<?php

namespace App\Evaluation;

final readonly class QualityReport
{
    /**
     * @param  list<ModelQuality>  $rows
     * @param  list<string>  $judges  judge model refs used in this run
     * @param  array<string, array{rho: ?float, n: int}>  $judgeAgreement  judge ref => Spearman with human ratings
     */
    public function __construct(
        public array $rows,
        public array $judges,
        public array $judgeAgreement,
        public int $votes,
        public int $topK,
    ) {}

    public function hasReference(): bool
    {
        return array_filter($this->rows, fn (ModelQuality $q) => $q->accuracy !== null) !== [];
    }
}
