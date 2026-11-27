<?php

namespace App\Llm;

use App\Contracts\LlmClientInterface;
use App\Llm\Dto\ChatRequest;
use App\Llm\Dto\ChatResponse;
use App\Llm\Dto\Usage;
use App\Llm\Exceptions\ProviderException;
use Illuminate\Http\Client\PendingRequest;

/**
 * One client for every provider speaking the OpenAI /chat/completions protocol.
 */
final class OpenAiCompatibleClient implements LlmClientInterface
{
    public function __construct(private readonly ProviderHttp $http) {}

    public function chat(ProviderConfig $provider, ChatRequest $request): ChatResponse
    {
        $startedAt = hrtime(true);
        $response = $this->http->send(
            $provider,
            fn (PendingRequest $pending) => $pending->post($provider->url('chat/completions'), $request->toPayload()),
        );
        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $data = $response->json();
        if (! is_array($data)) {
            throw new ProviderException("{$provider->code}: response is not JSON", $response->status());
        }

        // Some providers (OpenRouter) report upstream errors with HTTP 200.
        if (isset($data['error']) && ! isset($data['choices'])) {
            $status = (int) (data_get($data, 'error.code') ?: 502);
            throw $this->http->failure($provider, $status, $response->body(), $response->headers());
        }

        return new ChatResponse(
            content: data_get($data, 'choices.0.message.content'),
            usage: Usage::fromArray($data['usage'] ?? null),
            latencyMs: $latencyMs,
            httpStatus: $response->status(),
            finishReason: data_get($data, 'choices.0.finish_reason'),
            raw: $data,
        );
    }
}
