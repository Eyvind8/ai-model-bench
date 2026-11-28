<?php

namespace App\Evaluation;

use App\Support\Stats;

/**
 * How close model scores are to human reference scores (TZ 7.1.1).
 */
final class ReferenceAccuracyCalculator
{
    /**
     * @param  array<int, array{expected: int, predicted: float}>  $pairs  item id => scores
     */
    public function calculate(array $pairs, int $topK = 5): ?ReferenceAccuracy
    {
        if ($pairs === []) {
            return null;
        }

        $expected = array_map(fn (array $p) => (float) $p['expected'], $pairs);
        $predicted = array_map(fn (array $p) => $p['predicted'], $pairs);
        $errors = array_map(fn (array $p) => $p['predicted'] - $p['expected'], $pairs);
        $k = max(1, min($topK, count($pairs)));

        return new ReferenceAccuracy(
            count: count($pairs),
            mae: (float) Stats::mean(array_values(array_map('abs', $errors))),
            within1: count(array_filter($errors, fn (float $e) => abs($e) <= 1)) / count($pairs),
            bias: (float) Stats::mean(array_values($errors)),
            spearman: Stats::spearman(array_values($expected), array_values($predicted)),
            topKOverlap: count(array_intersect($this->top($expected, $k), $this->top($predicted, $k))) / $k,
            k: $k,
        );
    }

    /**
     * Ids of the K highest scores; ties keep item order (stable sort).
     *
     * @param  array<int, float>  $scores
     * @return list<int>
     */
    private function top(array $scores, int $k): array
    {
        $ids = array_keys($scores);
        usort($ids, fn (int $a, int $b) => $scores[$b] <=> $scores[$a] ?: $a <=> $b);

        return array_slice($ids, 0, $k);
    }
}
