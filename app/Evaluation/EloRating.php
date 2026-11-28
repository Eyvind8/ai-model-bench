<?php

namespace App\Evaluation;

final class EloRating
{
    public function __construct(
        public float $rating,
        public int $wins = 0,
        public int $losses = 0,
        public int $ties = 0,
        public int $bothBad = 0,
    ) {}

    public function games(): int
    {
        return $this->wins + $this->losses + $this->ties;
    }
}
