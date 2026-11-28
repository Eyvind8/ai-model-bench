<?php

namespace App\Contracts;

use App\Metrics\MetricContext;
use App\Metrics\MetricValue;

interface MetricInterface
{
    /**
     * @param  array<string, mixed>  $options  scenario options of this metric (field, max, ...)
     * @return MetricValue|null null when the metric does not apply (no field, no reference)
     */
    public function compute(MetricContext $context, array $options): ?MetricValue;
}
