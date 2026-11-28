<?php

namespace Tests\Unit\RateLimit;

use App\Llm\Dto\ChatRequest;
use App\RateLimit\TokenEstimator;
use PHPUnit\Framework\TestCase;

class TokenEstimatorTest extends TestCase
{
    public function test_estimates_prompt_by_chars_plus_max_tokens(): void
    {
        $request = new ChatRequest('m', [
            ['role' => 'system', 'content' => str_repeat('a', 400)],
            ['role' => 'user', 'content' => str_repeat('б', 400)],
        ], maxTokens: 300);

        $this->assertSame(200 + 300, (new TokenEstimator)->estimate($request));
    }

    public function test_default_completion_budget_without_max_tokens(): void
    {
        $request = new ChatRequest('m', [['role' => 'user', 'content' => 'abcd']]);

        $this->assertSame(1 + TokenEstimator::DEFAULT_COMPLETION_TOKENS, (new TokenEstimator)->estimate($request));
    }
}
