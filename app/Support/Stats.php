<?php

namespace App\Support;

final class Stats
{
    /**
     * Nearest-rank percentile.
     *
     * @param  list<int|float>  $values
     */
    public static function percentile(array $values, float $percent): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $rank = (int) ceil($percent / 100 * count($values));

        return (float) $values[max(0, min(count($values) - 1, $rank - 1))];
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function mean(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Population standard deviation; null for fewer than 2 values.
     *
     * @param  list<int|float>  $values
     */
    public static function stddev(array $values): ?float
    {
        if (count($values) < 2) {
            return null;
        }

        $mean = array_sum($values) / count($values);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values));
    }

    /**
     * 1-based ranks, ties get the average rank.
     *
     * @param  list<int|float>  $values
     * @return list<float>
     */
    public static function ranks(array $values): array
    {
        $order = array_keys($values);
        usort($order, fn (int $a, int $b) => $values[$a] <=> $values[$b]);

        $ranks = [];
        for ($i = 0, $n = count($order); $i < $n;) {
            $j = $i;
            while ($j + 1 < $n && $values[$order[$j + 1]] == $values[$order[$i]]) {
                $j++;
            }
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$order[$k]] = ($i + $j) / 2.0 + 1.0;
            }
            $i = $j + 1;
        }
        ksort($ranks);

        return array_values($ranks);
    }

    /**
     * Spearman rank correlation (Pearson on ranks); null for < 3 points or a constant series.
     *
     * @param  list<int|float>  $x
     * @param  list<int|float>  $y
     */
    public static function spearman(array $x, array $y): ?float
    {
        if (count($x) !== count($y) || count($x) < 3) {
            return null;
        }

        return self::pearson(self::ranks($x), self::ranks($y));
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private static function pearson(array $x, array $y): ?float
    {
        $mx = array_sum($x) / count($x);
        $my = array_sum($y) / count($y);
        $cov = $vx = $vy = 0.0;

        foreach ($x as $i => $xi) {
            $cov += ($xi - $mx) * ($y[$i] - $my);
            $vx += ($xi - $mx) ** 2;
            $vy += ($y[$i] - $my) ** 2;
        }

        return $vx == 0.0 || $vy == 0.0 ? null : $cov / sqrt($vx * $vy);
    }

    /**
     * Share of true among non-null flags.
     *
     * @param  list<bool|null>  $flags
     */
    public static function rate(array $flags): ?float
    {
        $known = array_values(array_filter($flags, fn ($f) => $f !== null));

        return $known === [] ? null : count(array_filter($known)) / count($known);
    }
}
