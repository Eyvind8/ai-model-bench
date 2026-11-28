<?php

namespace App\Metrics;

use App\Contracts\MetricInterface;

/**
 * 1 when the field length (characters) is within `max`, else 0. Options: field, max.
 */
final class MaxLengthMetric implements MetricInterface
{
    public function compute(MetricContext $context, array $options): ?MetricValue
    {
        $value = $context->field((string) ($options['field'] ?? ''));
        if (! is_string($value)) {
            return null;
        }

        $length = mb_strlen($value);

        return new MetricValue($length <= (int) ($options['max'] ?? PHP_INT_MAX) ? 1.0 : 0.0, "{$length} chars");
    }
}
