<?php

namespace App\Runs\Report;

use App\Enums\EvaluationType;
use App\Models\Result;
use App\Models\Run;
use App\Scenarios\ScenarioDisplay;
use Generator;

/**
 * Flat CSV rows of a run: one line per result with answer fields and auto metrics.
 */
final class ResultsCsvExporter
{
    private const BASE_COLUMNS = [
        'model', 'item_id', 'title', 'attempt', 'status', 'score', 'expected_score', 'json_valid', 'schema_valid',
        'latency_ms', 'prompt_tokens', 'completion_tokens', 'text', 'error',
    ];

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(Run $run): Generator
    {
        $display = new ScenarioDisplay($run->definition());
        $metrics = array_keys($run->definition()->metrics);

        yield [...self::BASE_COLUMNS, ...$metrics];

        $results = $run->results()->with(['model.provider', 'datasetItem', 'evaluations'])->orderBy('id')->lazy();
        foreach ($results as $result) {
            yield [...$this->base($result, $display), ...$this->metrics($result, $metrics)];
        }
    }

    /**
     * @return list<string|int|float|null>
     */
    private function base(Result $result, ScenarioDisplay $display): array
    {
        $field = fn (?string $name) => $name !== null ? ($result->parsed_json[$name] ?? null) : null;

        return [
            $result->model->ref(),
            $result->dataset_item_id,
            $result->datasetItem?->title,
            $result->attempt_no,
            $result->status->value,
            $field($display->scoreField()),
            $result->datasetItem?->expected_score,
            $this->flag($result->json_valid),
            $this->flag($result->schema_valid),
            $result->latency_ms,
            $result->prompt_tokens,
            $result->completion_tokens,
            $field($display->textField()) ?? $result->content,
            $result->error_message,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @return list<float|null>
     */
    private function metrics(Result $result, array $labels): array
    {
        $values = $result->evaluations->where('type', EvaluationType::Auto)->pluck('value', 'metric');

        return array_map(fn (string $label) => $values[$label] ?? null, $labels);
    }

    private function flag(?bool $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
