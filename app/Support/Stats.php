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
