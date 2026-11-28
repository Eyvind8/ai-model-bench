<?php

namespace App\Evaluation\Judge;

use App\Enums\EvaluationType;
use App\Models\LlmModel;
use App\Models\Result;
use Illuminate\Support\Facades\DB;

/**
 * Stores judge evaluations: judge_overall + judge:<criterion>, or judge_error. Re-judging replaces.
 */
final class JudgeRecorder
{
    public const OVERALL = 'judge_overall';

    public const ERROR = 'judge_error';

    public const CRITERION_PREFIX = 'judge:';

    public function verdict(Result $result, LlmModel $judge, JudgeVerdict $verdict): void
    {
        DB::transaction(function () use ($result, $judge, $verdict) {
            $this->clear($result, $judge);
            $this->store($result, $judge, self::OVERALL, $verdict->overall, $verdict->comment);
            foreach ($verdict->scores as $criterion => $score) {
                $this->store($result, $judge, self::CRITERION_PREFIX.$criterion, $score, null);
            }
        });
    }

    public function error(Result $result, LlmModel $judge, string $message): void
    {
        DB::transaction(function () use ($result, $judge, $message) {
            $this->clear($result, $judge);
            $this->store($result, $judge, self::ERROR, null, mb_substr($message, 0, 500));
        });
    }

    private function clear(Result $result, LlmModel $judge): void
    {
        $result->evaluations()->where('type', EvaluationType::Judge)->where('judge_model_id', $judge->id)->delete();
    }

    private function store(Result $result, LlmModel $judge, string $metric, ?float $value, ?string $details): void
    {
        $result->evaluations()->create([
            'type' => EvaluationType::Judge,
            'metric' => $metric,
            'value' => $value,
            'details' => $details,
            'judge_model_id' => $judge->id,
        ]);
    }
}
