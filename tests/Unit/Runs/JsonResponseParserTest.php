<?php

namespace Tests\Unit\Runs;

use App\Runs\Execution\JsonResponseParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsonResponseParserTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_extract(?string $content, ?string $expected): void
    {
        $this->assertSame($expected, (new JsonResponseParser)->extract($content));
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function cases(): array
    {
        return [
            'plain' => ['{"score":7}', '{"score":7}'],
            'whitespace' => ["  \n{\"score\":7}\n", '{"score":7}'],
            'fenced json' => ["Here:\n```json\n{\"score\":7}\n```\nDone", '{"score":7}'],
            'fenced no lang' => ["```\n{\"a\":1}\n```", '{"a":1}'],
            'think block' => ["<think>I think {maybe} 5</think>\n{\"score\":5}", '{"score":5}'],
            'text around' => ['Sure! {"score": 3, "announcement": "a"} Hope it helps', '{"score": 3, "announcement": "a"}'],
            'braces inside strings' => ['x {"announcement": "use {braces} and \\"quotes\\" }", "score": 2} y', '{"announcement": "use {braces} and \\"quotes\\" }", "score": 2}'],
            'nested' => ['{"a":{"b":1}} tail', '{"a":{"b":1}}'],
            'array is not object' => ['[1,2]', null],
            'broken' => ['{"score": 7', null],
            'no json' => ['I cannot answer', null],
            'null' => [null, null],
        ];
    }
}
