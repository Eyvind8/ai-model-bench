<?php

namespace App\Metrics;

use App\Contracts\MetricInterface;

/**
 * |model score - human reference score|. Options: field (default "score").
 */
final class ScoreErrorMetric implements MetricInterface
{
    public function compute(MetricContext $context, array $options): ?MetricValue
    {
        $score = $context->field((string) ($options['field'] ?? 'score'));
        $expected = $context->item?->expected_score;

        if (! is_numeric($score) || $expected === null) {
            return null;
        }

        return new MetricValue(abs((float) $score - $expected), "model {$score}, expected {$expected}");
    }
}
