<?php

namespace App\Runs\Execution;

use App\Contracts\RateLimiterInterface;
use App\Enums\JsonMode;
use App\Enums\ResponseFormat;
use App\Enums\ResultStatus;
use App\Llm\Dto\ChatRequest;
use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use App\Llm\ProviderConfig;
use App\Models\Result;
use App\RateLimit\RetryPolicy;

/**
 * Decides what to do after a provider error: JSON fallback, retry later, or give up.
 */
final class FailureResolver
{
    private const BAD_REQUEST = 400;

    public function __construct(
        private readonly RetryPolicy $policy,
        private readonly RateLimiterInterface $limiter,
        private readonly ResultRecorder $recorder,
    ) {}

    /**
     * @return int|null ms to wait before the next attempt, null = result is final
     */
    public function resolve(Result $result, ProviderConfig $provider, ProviderException $error): ?int
    {
        $request = ChatRequest::fromPayload($result->request_json);

        if ($error->httpStatus === self::BAD_REQUEST && $request->responseFormat !== ResponseFormat::Text) {
            $result->update(['request_json' => $request->withoutResponseFormat()->toPayload(), 'json_mode' => JsonMode::Fallback]);

            return 0;
        }

        $retryNo = $result->retries + 1;
        if ($this->policy->shouldRetry($error, $retryNo)) {
            $result->update(['retries' => $retryNo, 'error_message' => $error->getMessage()]);

            return $this->policy->delayMs($error, $retryNo);
        }

        $rateLimited = $error instanceof RateLimitedException;
        if ($rateLimited) {
            $this->limiter->markExhausted($provider, $request->model);
        }

        $this->recorder->failure($result, $rateLimited ? ResultStatus::RateLimited : ResultStatus::Error, $error->getMessage(), $error->httpStatus);

        return null;
    }
}
