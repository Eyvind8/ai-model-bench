<?php

namespace App\Services\Ping;

use App\Enums\PingStatus;
use App\Llm\DriverResolver;
use App\Llm\Dto\ChatRequest;
use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use Generator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Sleep;

/**
 * Sends a minimal request to each model to check key, model ID and availability.
 */
final class ModelPinger
{
    public function __construct(
        private readonly DriverResolver $drivers,
        private readonly Repository $config,
    ) {}

    /**
     * Pauses `min_interval_ms` between consecutive calls to the same provider.
     *
     * @param  list<PingTarget>  $targets
     * @return Generator<int, PingResult>
     */
    public function pingAll(array $targets): Generator
    {
        $lastProvider = null;

        foreach ($targets as $target) {
            if ($lastProvider === $target->provider->code) {
                $this->pause($target);
            }

            yield $this->ping($target);
            $lastProvider = $target->provider->code;
        }
    }

    public function ping(PingTarget $target): PingResult
    {
        $request = new ChatRequest(
            model: $target->modelId,
            messages: [['role' => 'user', 'content' => (string) $this->config->get('bench.ping.prompt')]],
            maxTokens: (int) $this->config->get('bench.ping.max_tokens', 64),
        );

        try {
            $response = $this->drivers->client($target->provider)->chat($target->provider, $request);
        } catch (RateLimitedException $e) {
            return new PingResult($target, PingStatus::RateLimited, error: $e->getMessage());
        } catch (ProviderException $e) {
            return new PingResult($target, PingStatus::Error, error: $e->getMessage());
        }

        if (trim((string) $response->content) === '') {
            return new PingResult($target, PingStatus::Empty, $response->latencyMs,
                error: 'no text in reply, finish_reason: '.($response->finishReason ?? 'unknown'));
        }

        return new PingResult($target, PingStatus::Ok, $response->latencyMs, $response->content);
    }

    private function pause(PingTarget $target): void
    {
        $ms = (int) ($target->provider->limitsFor($target->modelId)['min_interval_ms'] ?? 0);
        if ($ms > 0) {
            Sleep::for($ms)->milliseconds();
        }
    }
}
