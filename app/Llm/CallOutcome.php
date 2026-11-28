<?php

namespace App\Llm;

use App\Llm\Dto\ChatResponse;
use App\Llm\Exceptions\ProviderException;

/**
 * Result of a rate-limited chat call: a response, a wait, a provider error, or a final refusal.
 */
final readonly class CallOutcome
{
    private function __construct(
        public ?ChatResponse $response = null,
        public ?int $waitMs = null,
        public ?ProviderException $error = null,
        public ?string $refusal = null,
        public bool $exhausted = false,
        public ?ProviderConfig $provider = null,
    ) {}

    public static function response(ChatResponse $response, ProviderConfig $provider): self
    {
        return new self(response: $response, provider: $provider);
    }

    public static function wait(int $ms): self
    {
        return new self(waitMs: $ms);
    }

    public static function error(ProviderException $error, ProviderConfig $provider): self
    {
        return new self(error: $error, provider: $provider);
    }

    /**
     * Not sent at all: provider removed from config, or daily quota exhausted.
     */
    public static function refused(string $reason, bool $exhausted = false): self
    {
        return new self(refusal: $reason, exhausted: $exhausted);
    }
}
