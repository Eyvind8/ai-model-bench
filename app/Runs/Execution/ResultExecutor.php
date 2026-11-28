<?php

namespace App\Runs\Execution;

use App\Enums\ResultStatus;
use App\Llm\Dto\ChatRequest;
use App\Llm\LimitedChatCaller;
use App\Models\Result;
use App\Runs\RunProgress;

/**
 * Executes one pending result: rate-limited provider call, then recording.
 */
final class ResultExecutor
{
    public function __construct(
        private readonly LimitedChatCaller $caller,
        private readonly ResultRecorder $recorder,
        private readonly FailureResolver $failures,
        private readonly RunProgress $progress,
    ) {}

    /**
     * @return int|null ms to wait before calling again, null when the result is final
     */
    public function execute(Result $result): ?int
    {
        if ($result->status !== ResultStatus::Pending) {
            return null;
        }

        $result->loadMissing(['model.provider', 'run', 'datasetItem']);
        $this->progress->markStarted($result->run);

        $delay = $this->attempt($result);
        if ($delay === null) {
            $this->progress->refresh($result->run);
        }

        return $delay;
    }

    private function attempt(Result $result): ?int
    {
        $outcome = $this->caller->call($result->model->provider->code, ChatRequest::fromPayload($result->request_json));

        if ($outcome->waitMs !== null) {
            return $outcome->waitMs;
        }
        if ($outcome->error !== null && $outcome->provider !== null) {
            return $this->failures->resolve($result, $outcome->provider, $outcome->error);
        }

        $outcome->response !== null
            ? $this->recorder->success($result, $outcome->response, $result->run->definition())
            : $this->recorder->failure($result, $outcome->exhausted ? ResultStatus::RateLimited : ResultStatus::Error, (string) $outcome->refusal);

        return null;
    }
}
