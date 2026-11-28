<?php

namespace App\Runs\Report;

use App\Models\LlmModel;
use App\Models\Result;
use App\Models\Run;
use App\Scenarios\ScenarioDisplay;
use Illuminate\Support\Collection;

/**
 * Input x model grid of a run (all attempts per cell) for side-by-side answers and the score heatmap.
 */
final class RunMatrixBuilder
{
    public function build(Run $run): RunMatrix
    {
        $results = $run->results()->with(['model.provider', 'datasetItem', 'evaluations'])->orderBy('attempt_no')->get();
        $display = new ScenarioDisplay($run->definition());

        /** @var list<LlmModel> $models */
        $models = $results->pluck('model')->unique('id')->sortBy(fn (LlmModel $m) => $m->ref())->values()->all();

        $rows = $results
            ->groupBy(fn (Result $r) => $r->dataset_item_id ?? 0)
            ->sortKeys()
            ->map(fn (Collection $group) => $this->row($group, $run))
            ->values()
            ->all();

        return new RunMatrix($rows, $models, $display->scoreField(), $display->textField());
    }

    /**
     * @param  Collection<int, Result>  $results
     */
    private function row(Collection $results, Run $run): MatrixRow
    {
        $item = $results->first()->datasetItem;

        return new MatrixRow(
            item: $item,
            label: $item !== null ? ($item->title ?: "#{$item->id}") : 'Ad-hoc: '.mb_substr((string) $run->adhoc_input, 0, 60),
            input: $item !== null ? $item->input : (string) $run->adhoc_input,
            expectedScore: $item?->expected_score,
            cells: $results->groupBy('model_id')->map(fn (Collection $c) => $c->values()->all())->all(),
        );
    }
}
