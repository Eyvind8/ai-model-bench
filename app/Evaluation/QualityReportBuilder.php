<?php

namespace App\Evaluation;

use App\Enums\EvaluationType;
use App\Evaluation\Arena\ArenaService;
use App\Evaluation\Judge\JudgeRecorder;
use App\Models\Evaluation;
use App\Models\LlmModel;
use App\Models\Run;
use App\Runs\HumanRating;
use App\Runs\Report\MatrixRow;
use App\Runs\Report\RunMatrix;
use App\Runs\Report\RunMatrixBuilder;
use App\Support\Stats;
use Illuminate\Support\Collection;

/**
 * Stage 4 quality view of a run: reference accuracy, consistency, judge, human rating, Elo.
 */
final class QualityReportBuilder
{
    public const TOP_K = 5;

    public function __construct(
        private readonly RunMatrixBuilder $matrices,
        private readonly ReferenceAccuracyCalculator $accuracy,
        private readonly ConsistencyCalculator $consistency,
        private readonly ArenaService $arena,
    ) {}

    public function build(Run $run, ?RunMatrix $matrix = null): QualityReport
    {
        $matrix ??= $this->matrices->build($run);
        $evaluations = Evaluation::query()->whereIn('result_id', $run->results()->select('id'))
            ->whereIn('type', [EvaluationType::Judge, EvaluationType::Human])->with('result')->get();
        $elo = $this->arena->leaderboard($run);
        $deviation = $this->consistency->deviationFromMedian($this->meanScores($matrix));

        $judges = $this->judges($evaluations);
        $rows = array_map(fn (LlmModel $model) => new ModelQuality(
            model: $model,
            accuracy: $this->accuracy->calculate($this->referencePairs($matrix, $model), self::TOP_K),
            repeatSpread: $this->consistency->repeatSpread($this->attemptScores($matrix, $model)),
            medianDeviation: $deviation[$model->ref()] ?? null,
            judgeScores: $this->judgeScores($evaluations, $model, $judges),
            humanRating: $this->mean($evaluations, $model, EvaluationType::Human, HumanRating::METRIC),
            elo: $elo[$model->ref()] ?? null,
        ), $matrix->models);

        return new QualityReport(
            rows: $rows,
            judges: array_values($judges),
            judgeAgreement: array_map(fn (int $judgeId) => $this->judgeHumanAgreement($evaluations, $judgeId), array_flip($judges)),
            votes: $this->arena->votesCount($run),
            topK: self::TOP_K,
        );
    }

    /**
     * @return array<int, array{expected: int, predicted: float}>
     */
    private function referencePairs(RunMatrix $matrix, LlmModel $model): array
    {
        $pairs = [];
        foreach ($matrix->rows as $row) {
            $score = $row->meanScore($model->id, $matrix->scoreField);
            if ($row->item !== null && $row->expectedScore !== null && $score !== null) {
                $pairs[$row->item->id] = ['expected' => $row->expectedScore, 'predicted' => $score];
            }
        }

        return $pairs;
    }

    /**
     * @return array<int, list<float>>
     */
    private function attemptScores(RunMatrix $matrix, LlmModel $model): array
    {
        return array_map(fn (MatrixRow $row) => $row->scores($model->id, $matrix->scoreField), $matrix->rows);
    }

    /**
     * @return array<int, array<string, float>>
     */
    private function meanScores(RunMatrix $matrix): array
    {
        $scores = [];
        foreach ($matrix->rows as $i => $row) {
            foreach ($matrix->models as $model) {
                $score = $row->meanScore($model->id, $matrix->scoreField);
                if ($score !== null) {
                    $scores[$i][$model->ref()] = $score;
                }
            }
        }

        return $scores;
    }

    /**
     * @param  Collection<int, Evaluation>  $evaluations
     */
    private function mean(Collection $evaluations, LlmModel $model, EvaluationType $type, string $metric, ?int $judgeId = null): ?float
    {
        $values = $evaluations->filter(fn (Evaluation $e) => $e->type === $type && $e->metric === $metric
            && $e->value !== null && $e->result->model_id === $model->id
            && ($judgeId === null || $e->judge_model_id === $judgeId))->pluck('value');

        return Stats::mean($values->values()->all());
    }

    /**
     * Per judge, never averaged across judges: each judge skips its own answers, so a mix would compare apples to oranges.
     *
     * @param  Collection<int, Evaluation>  $evaluations
     * @param  array<int, string>  $judges  judge model id => ref
     * @return array<string, float>
     */
    private function judgeScores(Collection $evaluations, LlmModel $model, array $judges): array
    {
        $scores = [];
        foreach ($judges as $judgeId => $ref) {
            $mean = $this->mean($evaluations, $model, EvaluationType::Judge, JudgeRecorder::OVERALL, $judgeId);
            if ($mean !== null) {
                $scores[$ref] = $mean;
            }
        }

        return $scores;
    }

    /**
     * Spearman between one judge's overall score and human rating on answers that have both.
     *
     * @param  Collection<int, Evaluation>  $evaluations
     * @return array{rho: ?float, n: int}
     */
    private function judgeHumanAgreement(Collection $evaluations, int $judgeId): array
    {
        $judge = $evaluations->where('metric', JudgeRecorder::OVERALL)->where('judge_model_id', $judgeId)
            ->whereNotNull('value')->pluck('value', 'result_id');
        $human = $evaluations->where('type', EvaluationType::Human)->pluck('value', 'result_id');
        $common = $judge->keys()->intersect($human->keys())->values();

        return [
            'rho' => Stats::spearman($common->map(fn ($id) => (float) $judge[$id])->all(), $common->map(fn ($id) => (float) $human[$id])->all()),
            'n' => $common->count(),
        ];
    }

    /**
     * @param  Collection<int, Evaluation>  $evaluations
     * @return array<int, string> judge model id => ref
     */
    private function judges(Collection $evaluations): array
    {
        $ids = $evaluations->where('type', EvaluationType::Judge)->pluck('judge_model_id')->filter()->unique()->values()->all();

        return LlmModel::query()->with('provider')->whereKey($ids)->orderBy('id')->get()
            ->mapWithKeys(fn (LlmModel $m) => [$m->id => $m->ref()])->all();
    }
}
