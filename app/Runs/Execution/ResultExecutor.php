<?php

namespace App\Runs\Execution;

use App\Contracts\RateLimiterInterface;
use App\Enums\ResultStatus;
use App\Llm\DriverResolver;
use App\Llm\Dto\ChatRequest;
use App\Llm\Exceptions\ProviderException;
use App\Llm\ProviderConfig;
use App\Llm\ProviderRegistry;
use App\Models\Result;
use App\RateLimit\TokenEstimator;
use App\Runs\RunProgress;

/**
 * Executes one pending result: rate limit check, provider call, recording.
 */
final class ResultExecutor
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly DriverResolver $drivers,
        private readonly RateLimiterInterface $limiter,
        private readonly TokenEstimator $tokens,
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
        $code = $result->model->provider->code;
        if (! $this->registry->has($code)) {
            $this->recorder->failure($result, ResultStatus::Error, "Provider {$code} is not in config anymore");

            return null;
        }

        $provider = $this->registry->get($code);
        $request = ChatRequest::fromPayload($result->request_json);
        $decision = $this->limiter->acquire($provider, $request->model, $this->tokens->estimate($request));

        if ($decision->exhausted) {
            $this->recorder->failure($result, ResultStatus::RateLimited, (string) $decision->reason);

            return null;
        }

        return $decision->allowed ? $this->call($result, $provider, $request) : $decision->waitMs;
    }

    private function call(Result $result, ProviderConfig $provider, ChatRequest $request): ?int
    {
        try {
            $response = $this->drivers->client($provider)->chat($provider, $request);
        } catch (ProviderException $e) {
            return $this->failures->resolve($result, $provider, $e);
        }

        $this->recorder->success($result, $response, $result->run->definition());

        return null;
    }
}
