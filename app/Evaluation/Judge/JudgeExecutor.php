<?php

namespace App\Evaluation\Judge;

use App\Enums\EvaluationType;
use App\Llm\Dto\ChatResponse;
use App\Llm\Exceptions\ProviderException;
use App\Llm\LimitedChatCaller;
use App\Models\LlmModel;
use App\Models\Result;
use App\RateLimit\RetryPolicy;
use Illuminate\Contracts\Cache\Repository;

/**
 * Asks the judge model to rate one answer. Same rate limiting and retry rules as runs.
 * Idempotent: an answer already rated by this judge is skipped (double click, CLI after UI).
 */
final class JudgeExecutor
{
    private const RETRY_TTL = 86400;

    public function __construct(
        private readonly LimitedChatCaller $caller,
        private readonly RetryPolicy $retries,
        private readonly JudgePromptBuilder $prompts,
        private readonly JudgeVerdictParser $parser,
        private readonly JudgeRecorder $recorder,
        private readonly Repository $cache,
    ) {}

    /**
     * @return int|null ms to wait before calling again, null when done (verdict or error stored)
     */
    public function execute(Result $result, LlmModel $judge): ?int
    {
        if ($this->alreadyJudged($result, $judge)) {
            return null;
        }

        $result->loadMissing(['run', 'datasetItem']);
        $judge->loadMissing('provider');
        $outcome = $this->caller->call($judge->provider->code, $this->prompts->build($result->run->definition(), $result, $judge));

        if ($outcome->waitMs !== null) {
            return $outcome->waitMs;
        }
        if ($outcome->error !== null) {
            return $this->failure($result, $judge, $outcome->error);
        }

        $outcome->response !== null
            ? $this->record($result, $judge, $outcome->response)
            : $this->recorder->error($result, $judge, (string) $outcome->refusal);

        return null;
    }

    private function alreadyJudged(Result $result, LlmModel $judge): bool
    {
        return $result->evaluations()->where('type', EvaluationType::Judge)
            ->where('judge_model_id', $judge->id)->where('metric', JudgeRecorder::OVERALL)->exists();
    }

    private function record(Result $result, LlmModel $judge, ChatResponse $response): void
    {
        $this->cache->forget($this->retryKey($result, $judge));
        $verdict = $this->parser->parse($response->content, JudgeCriteria::fromRubric($result->run->definition()->judgeRubric));

        $verdict !== null
            ? $this->recorder->verdict($result, $judge, $verdict)
            : $this->recorder->error($result, $judge, 'judge reply is not a valid verdict: '.mb_substr((string) $response->content, 0, 200));
    }

    private function failure(Result $result, LlmModel $judge, ProviderException $error): ?int
    {
        $key = $this->retryKey($result, $judge);
        $retryNo = (int) $this->cache->get($key, 0) + 1;

        if ($this->retries->shouldRetry($error, $retryNo)) {
            $this->cache->put($key, $retryNo, self::RETRY_TTL);

            return $this->retries->delayMs($error, $retryNo);
        }

        $this->cache->forget($key);
        $this->recorder->error($result, $judge, $error->getMessage());

        return null;
    }

    private function retryKey(Result $result, LlmModel $judge): string
    {
        return "bench:judge-retry:{$result->id}:{$judge->id}";
    }
}
