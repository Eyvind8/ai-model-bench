<?php

namespace App\Evaluation;

use App\Support\Stats;

/**
 * Score stability (TZ 7.4): within a model across repeats, and between models.
 */
final class ConsistencyCalculator
{
    /**
     * Mean standard deviation of one model's scores over repeats of the same item.
     *
     * @param  array<int, list<int|float>>  $scoresByItem
     */
    public function repeatSpread(array $scoresByItem): ?float
    {
        $spreads = array_values(array_filter(
            array_map(fn (array $scores) => Stats::stddev($scores), $scoresByItem),
            fn (?float $s) => $s !== null,
        ));

        return Stats::mean($spreads);
    }

    /**
     * Mean |model score - median of all models| per model, over items scored by 2+ models.
     *
     * @param  array<int, array<string, float>>  $scoresByItem  item => model => mean score
     * @return array<string, float>
     */
    public function deviationFromMedian(array $scoresByItem): array
    {
        $deviations = [];
        foreach ($scoresByItem as $scores) {
            if (count($scores) < 2) {
                continue;
            }
            $median = (float) Stats::median(array_values($scores));
            foreach ($scores as $model => $score) {
                $deviations[$model][] = abs($score - $median);
            }
        }

        return array_map(fn (array $d) => (float) Stats::mean($d), $deviations);
    }
}
