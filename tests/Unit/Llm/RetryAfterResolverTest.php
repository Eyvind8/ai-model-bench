<?php

namespace Tests\Unit\Llm;

use App\Llm\RetryAfterResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetryAfterResolverTest extends TestCase
{
    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('cases')]
    public function test_resolve(array $headers, string $body, ?int $expected): void
    {
        $this->assertSame($expected, (new RetryAfterResolver)->resolve($headers, $body));
    }

    /**
     * @return array<string, array{array<string, string>, string, ?int}>
     */
    public static function cases(): array
    {
        return [
            'retry-after seconds' => [['Retry-After' => '12'], '', 12],
            'header case-insensitive' => [['retry-after' => '3'], '', 3],
            'groq reset with minutes' => [['x-ratelimit-reset-requests' => '2m59.56s'], '', 180],
            'groq reset ms' => [['x-ratelimit-reset-tokens' => '250ms'], '', 1],
            'gemini retryDelay in body' => [[], '[{"error":{"details":[{"@type":"type.googleapis.com/google.rpc.RetryInfo","retryDelay":"31s"}]}}]', 31],
            'retry-after wins over body' => [['Retry-After' => '5'], '{"retryDelay":"31s"}', 5],
            'nothing' => [[], '{"error":"limit"}', null],
        ];
    }
}
