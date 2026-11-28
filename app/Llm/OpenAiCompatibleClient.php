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
    public function __construct(
        private readonly ProviderHttp $http,
        private readonly ChatCompletionMapper $mapper,
    ) {}

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

        $errorStatus = $this->mapper->embeddedErrorStatus($data);
        if ($errorStatus !== null) {
            throw $this->http->failure($provider, $errorStatus, $response->body(), $response->headers());
        }

        return new ChatResponse(
            content: $this->mapper->content($data),
            usage: Usage::fromArray($this->mapper->usage($data)),
            latencyMs: $latencyMs,
            httpStatus: $response->status(),
            finishReason: $this->mapper->finishReason($data),
            raw: $data,
        );
    }
}
