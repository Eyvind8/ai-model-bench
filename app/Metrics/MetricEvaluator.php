<?php

namespace App\Metrics;

use App\Contracts\MetricInterface;
use App\Enums\EvaluationType;
use App\Models\Result;
use App\Scenarios\ScenarioDefinition;
use App\Support\ConfiguredFactory;
use App\Support\InvalidConfigurationException;

/**
 * Computes the scenario's auto metrics for a result and stores them as evaluations (type=auto).
 */
final class MetricEvaluator
{
    public function __construct(private readonly ConfiguredFactory $factory) {}

    public function evaluate(Result $result, ScenarioDefinition $scenario): void
    {
        $context = new MetricContext($result->content, $result->parsed_json, $result->datasetItem);

        foreach ($scenario->metrics as $label => $options) {
            $value = $this->compute((string) ($options['metric'] ?? $label), $context, $options);

            if ($value !== null) {
                $result->evaluations()->create([
                    'type' => EvaluationType::Auto,
                    'metric' => $label,
                    'value' => $value->value,
                    'details' => $value->details,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function compute(string $metric, MetricContext $context, array $options): ?MetricValue
    {
        try {
            return $this->factory->make('bench.metrics', $metric, MetricInterface::class)->compute($context, $options);
        } catch (InvalidConfigurationException $e) {
            report($e);

            return null;
        }
    }
}
