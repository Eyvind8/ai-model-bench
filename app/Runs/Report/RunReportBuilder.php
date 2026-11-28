<?php

namespace App\Runs\Report;

use App\Enums\EvaluationType;
use App\Enums\ResultStatus;
use App\Models\Evaluation;
use App\Models\Result;
use App\Models\Run;
use App\Support\Stats;
use Illuminate\Support\Collection;

/**
 * Per-model summary of a run: validity, latency, errors and mean auto metrics.
 */
final class RunReportBuilder
{
    /**
     * @return list<ModelReport>
     */
    public function build(Run $run): array
    {
        $results = $run->results()->with(['model.provider', 'evaluations'])->get();

        return $results
            ->groupBy('model_id')
            ->map(fn (Collection $group) => $this->forModel($group, array_keys($run->definition()->metrics)))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Result>  $results
     * @param  list<string>  $metricLabels
     */
    private function forModel(Collection $results, array $metricLabels): ModelReport
    {
        $ok = $results->where('status', ResultStatus::Ok);
        $statuses = array_merge(
            array_fill_keys(array_map(fn (ResultStatus $s) => $s->value, ResultStatus::cases()), 0),
            $results->countBy(fn (Result $r) => $r->status->value)->all(),
        );
        $latencies = $ok->pluck('latency_ms')->filter(fn ($v) => $v !== null)->values()->all();

        return new ModelReport(
            model: $results->first()->model->ref(),
            total: $results->count(),
            statuses: $statuses,
            jsonValidRate: Stats::rate($ok->pluck('json_valid')->all()),
            schemaValidRate: Stats::rate($ok->pluck('schema_valid')->all()),
            latencyP50: Stats::percentile($latencies, 50),
            latencyP95: Stats::percentile($latencies, 95),
            avgCompletionTokens: Stats::mean($ok->pluck('completion_tokens')->filter(fn ($v) => $v !== null)->values()->all()),
            metrics: $this->metricMeans($ok, $metricLabels),
        );
    }

    /**
     * @param  Collection<int, Result>  $results
     * @param  list<string>  $labels
     * @return array<string, float|null>
     */
    private function metricMeans(Collection $results, array $labels): array
    {
        $evaluations = $results->flatMap(fn (Result $r) => $r->evaluations->where('type', EvaluationType::Auto));

        $means = [];
        foreach ($labels as $label) {
            $values = $evaluations->where('metric', $label)->map(fn (Evaluation $e) => $e->value)->filter(fn ($v) => $v !== null);
            $means[$label] = Stats::mean($values->values()->all());
        }

        return $means;
    }
}
