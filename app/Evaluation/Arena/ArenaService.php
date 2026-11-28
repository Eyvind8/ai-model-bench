<?php

namespace App\Evaluation\Arena;

use App\Enums\ResultStatus;
use App\Enums\VoteWinner;
use App\Evaluation\EloCalculator;
use App\Evaluation\EloRating;
use App\Models\PairwiseVote;
use App\Models\Result;
use App\Models\Run;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Blind A/B comparison: two answers to the same input from different models, names hidden.
 */
final class ArenaService
{
    public function __construct(private readonly EloCalculator $elo) {}

    /**
     * A random pair not voted yet, in random A/B order. Each pair is judged once, so Elo counts it once.
     *
     * @return array{Result, Result}|null
     */
    public function pick(Run $run): ?array
    {
        $voted = $this->votedKeys($run);
        $pair = $this->candidates($run)
            ->reject(fn (array $p) => isset($voted[$this->pairKey($p[0]->id, $p[1]->id)]))
            ->shuffle()
            ->first();

        return $pair === null ? null : collect($pair)->shuffle()->values()->all();
    }

    public function hasCandidates(Run $run): bool
    {
        return $this->candidates($run)->isNotEmpty();
    }

    public function vote(Run $run, int $resultA, int $resultB, VoteWinner $winner): PairwiseVote
    {
        if (isset($this->votedKeys($run)[$this->pairKey($resultA, $resultB)])) {
            throw new InvalidArgumentException('This pair has already been voted');
        }

        $results = Result::query()->where('run_id', $run->id)->where('status', ResultStatus::Ok)->whereKey([$resultA, $resultB])->get();

        if ($results->count() !== 2
            || $results[0]->dataset_item_id !== $results[1]->dataset_item_id
            || $results[0]->model_id === $results[1]->model_id) {
            throw new InvalidArgumentException('Answers must belong to this run, share the input and come from different models');
        }

        return PairwiseVote::query()->create(['run_id' => $run->id, 'result_a_id' => $resultA, 'result_b_id' => $resultB, 'winner' => $winner]);
    }

    /**
     * @return array<string, EloRating> model ref => rating
     */
    public function leaderboard(?Run $run = null): array
    {
        $votes = PairwiseVote::query()
            ->with(['resultA.model.provider', 'resultB.model.provider'])
            ->when($run, fn ($q) => $q->where('run_id', $run->id))
            ->orderBy('id')
            ->lazy()
            ->map(fn (PairwiseVote $v) => [$v->resultA->model->ref(), $v->resultB->model->ref(), $v->winner]);

        return $this->elo->ratings($votes);
    }

    public function votesCount(Run $run): int
    {
        return PairwiseVote::query()->where('run_id', $run->id)->count();
    }

    /**
     * @return Collection<int, array{Result, Result}>
     */
    private function candidates(Run $run): Collection
    {
        $results = $run->results()->where('status', ResultStatus::Ok)->whereNotNull('content')->with('datasetItem')->get();
        $pairs = collect();

        foreach ($results->groupBy(fn (Result $r) => $r->dataset_item_id ?? 0) as $group) {
            $list = $group->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    if ($list[$i]->model_id !== $list[$j]->model_id) {
                        $pairs->push([$list[$i], $list[$j]]);
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, bool>
     */
    private function votedKeys(Run $run): array
    {
        return PairwiseVote::query()->where('run_id', $run->id)->get(['result_a_id', 'result_b_id'])
            ->mapWithKeys(fn (PairwiseVote $v) => [$this->pairKey($v->result_a_id, $v->result_b_id) => true])
            ->all();
    }

    private function pairKey(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }
}
