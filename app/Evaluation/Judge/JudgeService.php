<?php

namespace App\Evaluation\Judge;

use App\Enums\EvaluationType;
use App\Enums\ResultStatus;
use App\Jobs\JudgeResultJob;
use App\Models\LlmModel;
use App\Models\Result;
use App\Models\Run;
use App\Support\InlineScheduler;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Judges all successful answers of a run that the judge has not rated yet.
 * The judge never rates its own answers (self-preference bias).
 */
final class JudgeService
{
    public function __construct(
        private readonly Dispatcher $bus,
        private readonly JudgeExecutor $executor,
        private readonly InlineScheduler $scheduler,
    ) {}

    /**
     * @return list<int> result ids
     */
    public function pending(Run $run, LlmModel $judge): array
    {
        return $run->results()
            ->where('status', ResultStatus::Ok)
            ->whereNotNull('content')
            ->where('model_id', '!=', $judge->id)
            ->whereDoesntHave('evaluations', fn ($q) => $q->where('type', EvaluationType::Judge)
                ->where('judge_model_id', $judge->id)
                ->where('metric', JudgeRecorder::OVERALL))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public function dispatch(Run $run, LlmModel $judge): int
    {
        $ids = $this->pending($run, $judge);
        foreach ($ids as $id) {
            $this->bus->dispatch(new JudgeResultJob($id, $judge->id));
        }

        return count($ids);
    }

    public function runInline(Run $run, LlmModel $judge): int
    {
        $ids = $this->pending($run, $judge);
        $judge->loadMissing('provider');

        $this->scheduler->run($ids, fn (int $id) => $this->executor->execute(Result::query()->findOrFail($id), $judge));

        return count($ids);
    }
}
