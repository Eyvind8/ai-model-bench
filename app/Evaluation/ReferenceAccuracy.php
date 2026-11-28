<?php

namespace App\Evaluation;

final readonly class ReferenceAccuracy
{
    public function __construct(
        public int $count,
        public float $mae,
        public float $within1,
        public float $bias,
        public ?float $spearman,
        public float $topKOverlap,
        public int $k,
    ) {}
}
