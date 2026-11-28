<?php

namespace App\RateLimit;

use App\Llm\Dto\ChatRequest;

/**
 * Rough token estimate for TPM accounting before the request is sent (~4 chars per token).
 */
final class TokenEstimator
{
    public const DEFAULT_COMPLETION_TOKENS = 512;

    private const CHARS_PER_TOKEN = 4;

    public function estimate(ChatRequest $request): int
    {
        $chars = array_sum(array_map(fn (array $m) => mb_strlen($m['content']), $request->messages));

        return (int) ceil($chars / self::CHARS_PER_TOKEN) + ($request->maxTokens ?? self::DEFAULT_COMPLETION_TOKENS);
    }
}
