<?php

namespace Tests\Unit\Models;

use App\Services\Models\ModelRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelRefTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_parse(string $input, ?string $provider, string $model): void
    {
        $ref = ModelRef::parse($input, ['groq', 'openrouter', 'cloudflare']);

        $this->assertSame($provider, $ref->provider);
        $this->assertSame($model, $ref->model);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function cases(): array
    {
        return [
            'provider and model' => ['groq:qwen/qwen3-32b', 'groq', 'qwen/qwen3-32b'],
            'model with colon' => ['openrouter:qwen/qwen3:free', 'openrouter', 'qwen/qwen3:free'],
            'cloudflare id' => ['cloudflare:@cf/meta/llama', 'cloudflare', '@cf/meta/llama'],
            'no provider' => ['*flash*', null, '*flash*'],
            'unknown prefix stays in model' => ['qwen/qwen3:free', null, 'qwen/qwen3:free'],
        ];
    }
}
