<?php

namespace Tests\Unit\Evaluation;

use App\Enums\VoteWinner;
use App\Evaluation\ConsistencyCalculator;
use App\Evaluation\EloCalculator;
use App\Evaluation\Judge\JudgeCriteria;
use App\Evaluation\ReferenceAccuracyCalculator;
use App\Support\Stats;
use PHPUnit\Framework\TestCase;

class CalculatorsTest extends TestCase
{
    public function test_ranks_average_ties(): void
    {
        $this->assertSame([1.0, 2.5, 2.5, 4.0], Stats::ranks([10, 20, 20, 30]));
        $this->assertSame([3.0, 1.0, 2.0], Stats::ranks([5, 1, 3]));
    }

    public function test_spearman(): void
    {
        $this->assertEqualsWithDelta(1.0, Stats::spearman([1, 2, 3, 4], [10, 20, 30, 40]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, Stats::spearman([1, 2, 3, 4], [9, 7, 5, 1]), 1e-9);
        $this->assertEqualsWithDelta(0.8, Stats::spearman([1, 2, 3, 4, 5], [2, 1, 4, 3, 5]), 1e-9);
        $this->assertNull(Stats::spearman([1, 2], [3, 4]), 'too few points');
        $this->assertNull(Stats::spearman([1, 2, 3], [5, 5, 5]), 'constant series');
    }

    public function test_median_and_stddev(): void
    {
        $this->assertSame(3.0, Stats::median([5, 1, 3]));
        $this->assertSame(2.5, Stats::median([4, 1, 3, 2]));
        $this->assertNull(Stats::median([]));
        $this->assertEqualsWithDelta(1.0, Stats::stddev([2, 4]), 1e-9);
        $this->assertSame(0.0, Stats::stddev([7, 7, 7]));
        $this->assertNull(Stats::stddev([5]));
    }

    public function test_reference_accuracy(): void
    {
        $pairs = [
            1 => ['expected' => 9, 'predicted' => 8.0],
            2 => ['expected' => 2, 'predicted' => 4.0],
            3 => ['expected' => 7, 'predicted' => 7.0],
            4 => ['expected' => 5, 'predicted' => 6.0],
        ];

        $a = (new ReferenceAccuracyCalculator)->calculate($pairs, topK: 2);

        $this->assertSame(4, $a->count);
        $this->assertEqualsWithDelta(1.0, $a->mae, 1e-9);           // (1+2+0+1)/4
        $this->assertEqualsWithDelta(0.75, $a->within1, 1e-9);      // 3 of 4 within ±1
        $this->assertEqualsWithDelta(0.5, $a->bias, 1e-9);          // (-1+2+0+1)/4
        $this->assertEqualsWithDelta(1.0, $a->spearman, 1e-9);      // same order
        $this->assertEqualsWithDelta(1.0, $a->topKOverlap, 1e-9);   // top-2 {1,3} both
        $this->assertSame(2, $a->k);
    }

    public function test_reference_accuracy_top_k_mismatch_and_empty(): void
    {
        $pairs = [1 => ['expected' => 10, 'predicted' => 1.0], 2 => ['expected' => 1, 'predicted' => 10.0], 3 => ['expected' => 5, 'predicted' => 5.0]];

        $a = (new ReferenceAccuracyCalculator)->calculate($pairs, topK: 1);

        $this->assertSame(0.0, $a->topKOverlap);
        $this->assertNull((new ReferenceAccuracyCalculator)->calculate([], 5));
    }

    public function test_consistency_between_repeats(): void
    {
        $calc = new ConsistencyCalculator;

        // item 1: [6, 8] -> std 1; item 2: [3, 3] -> 0; item 3: single -> ignored
        $this->assertEqualsWithDelta(0.5, $calc->repeatSpread([1 => [6, 8], 2 => [3, 3], 3 => [5]]), 1e-9);
        $this->assertNull($calc->repeatSpread([1 => [5], 2 => [7]]));
    }

    public function test_deviation_from_median_of_models(): void
    {
        $scores = [
            1 => ['a' => 8.0, 'b' => 7.0, 'c' => 2.0],   // median 7
            2 => ['a' => 5.0, 'b' => 5.0],              // median 5
        ];

        $deviation = (new ConsistencyCalculator)->deviationFromMedian($scores);

        $this->assertEqualsWithDelta(0.5, $deviation['a'], 1e-9);   // (1 + 0) / 2
        $this->assertEqualsWithDelta(0.0, $deviation['b'], 1e-9);
        $this->assertEqualsWithDelta(5.0, $deviation['c'], 1e-9);
    }

    public function test_judge_criteria_ignore_commas_inside_parentheses(): void
    {
        $criteria = JudgeCriteria::fromRubric('accuracy (no missing facts, no hallucinations), announcement style');

        $this->assertSame([
            'accuracy' => 'accuracy (no missing facts, no hallucinations)',
            'announcement_style' => 'announcement style',
        ], $criteria);
        $this->assertSame(['correctness', 'completeness', 'clarity'], array_keys(JudgeCriteria::fromRubric(null)));
    }

    public function test_elo(): void
    {
        $elo = new EloCalculator(k: 32, initial: 1000);

        $ratings = $elo->ratings([['a', 'b', VoteWinner::A]]);
        $this->assertEqualsWithDelta(1016.0, $ratings['a']->rating, 1e-9);
        $this->assertEqualsWithDelta(984.0, $ratings['b']->rating, 1e-9);
        $this->assertSame(1, $ratings['a']->wins);
        $this->assertSame(1, $ratings['b']->losses);

        $tie = $elo->ratings([['a', 'b', VoteWinner::Tie]]);
        $this->assertEqualsWithDelta(1000.0, $tie['a']->rating, 1e-9);
        $this->assertSame(1, $tie['a']->ties);

        $skipped = $elo->ratings([['a', 'b', VoteWinner::BothBad], ['a', 'c', VoteWinner::B]]);
        $this->assertEqualsWithDelta(1000.0, $skipped['b']->rating, 1e-9);
        $this->assertSame(1, $skipped['b']->bothBad);
        $this->assertGreaterThan(1000, $skipped['c']->rating);
    }
}
