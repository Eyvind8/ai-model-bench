<?php

namespace App\Llm;

use App\Contracts\RateLimiterInterface;
use App\Llm\Dto\ChatRequest;
use App\Llm\Exceptions\ProviderException;
use App\RateLimit\TokenEstimator;

/**
 * One chat call through the rate limiter: shared by run execution and the LLM judge.
 */
final class LimitedChatCaller
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly DriverResolver $drivers,
        private readonly RateLimiterInterface $limiter,
        private readonly TokenEstimator $tokens,
    ) {}

    public function call(string $providerCode, ChatRequest $request): CallOutcome
    {
        if (! $this->registry->has($providerCode)) {
            return CallOutcome::refused("Provider {$providerCode} is not in config anymore");
        }

        $provider = $this->registry->get($providerCode);
        $decision = $this->limiter->acquire($provider, $request->model, $this->tokens->estimate($request));

        if ($decision->exhausted) {
            return CallOutcome::refused((string) $decision->reason, exhausted: true);
        }
        if (! $decision->allowed) {
            return CallOutcome::wait($decision->waitMs);
        }

        try {
            return CallOutcome::response($this->drivers->client($provider)->chat($provider, $request), $provider);
        } catch (ProviderException $e) {
            return CallOutcome::error($e, $provider);
        }
    }
}
