<?php

namespace App\Evaluation;

use App\Enums\VoteWinner;

/**
 * Elo ratings from blind A/B votes, applied in chronological order. "both_bad" votes do not move ratings.
 */
final readonly class EloCalculator
{
    public function __construct(
        private float $k = 32,
        private float $initial = 1000,
    ) {}

    /**
     * @param  iterable<array{0: string, 1: string, 2: VoteWinner}>  $votes  [model A, model B, winner]
     * @return array<string, EloRating>
     */
    public function ratings(iterable $votes): array
    {
        $ratings = [];

        foreach ($votes as [$a, $b, $winner]) {
            $ra = $ratings[$a] ??= new EloRating($this->initial);
            $rb = $ratings[$b] ??= new EloRating($this->initial);

            if ($winner === VoteWinner::BothBad) {
                $ra->bothBad++;
                $rb->bothBad++;

                continue;
            }

            $scoreA = match ($winner) {
                VoteWinner::A => 1.0,
                VoteWinner::B => 0.0,
                default => 0.5,
            };
            $this->apply($ra, $rb, $scoreA);
        }

        uasort($ratings, fn (EloRating $x, EloRating $y) => $y->rating <=> $x->rating);

        return $ratings;
    }

    private function apply(EloRating $a, EloRating $b, float $scoreA): void
    {
        $expectedA = 1 / (1 + 10 ** (($b->rating - $a->rating) / 400));
        $delta = $this->k * ($scoreA - $expectedA);

        $a->rating += $delta;
        $b->rating -= $delta;

        match ($scoreA) {
            1.0 => [$a->wins++, $b->losses++],
            0.0 => [$a->losses++, $b->wins++],
            default => [$a->ties++, $b->ties++],
        };
    }
}
