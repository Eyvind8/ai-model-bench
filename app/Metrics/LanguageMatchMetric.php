<?php

namespace App\Metrics;

use App\Articles\LanguageDetector;
use App\Contracts\MetricInterface;

/**
 * 1 when the answer field is in the language of the article. Options: field (default: whole reply).
 */
final class LanguageMatchMetric implements MetricInterface
{
    public function __construct(private readonly LanguageDetector $detector) {}

    public function compute(MetricContext $context, array $options): ?MetricValue
    {
        $field = $options['field'] ?? null;
        $answer = $field !== null ? $context->field((string) $field) : $context->content;
        if (! is_string($answer) || $context->item === null) {
            return null;
        }

        $expected = $context->item->meta_json['lang'] ?? $this->detector->detect($context->item->input);
        $actual = $this->detector->detect($answer);

        if ($expected === null || $actual === null) {
            return null;
        }

        return new MetricValue($expected === $actual ? 1.0 : 0.0, "article {$expected}, answer {$actual}");
    }
}
