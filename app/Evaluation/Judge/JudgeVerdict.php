<?php

namespace App\Evaluation\Judge;

final readonly class JudgeVerdict
{
    /**
     * @param  array<string, float>  $scores  criterion key => 1-10
     */
    public function __construct(
        public float $overall,
        public array $scores,
        public ?string $comment,
    ) {}
}
