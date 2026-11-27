<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function fixture(string $path): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonFixture(string $path): array
    {
        return json_decode($this->fixture($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Replaces config providers with a deterministic test set:
     * `groq` (configured, catalog driver openai, one config model) and `gemini` (no key).
     *
     * @param  array<string, array<string, mixed>>  $overrides
     */
    protected function useTestProviders(array $overrides = []): void
    {
        config(['bench.providers' => array_replace_recursive([
            'groq' => [
                'name' => 'Groq',
                'driver' => 'openai',
                'catalog' => 'openai',
                'base_url' => 'https://api.groq.com/openai/v1',
                'api_key' => 'gk',
                'api_key_env' => 'GROQ_API_KEY',
                'exclude' => ['/whisper/i', '/guard/i'],
                'limits' => ['rpm' => 30, 'min_interval_ms' => 2000],
                'models' => [
                    'qwen/qwen3-32b' => ['name' => 'Qwen3 32B', 'json' => true],
                ],
            ],
            'gemini' => [
                'name' => 'Google AI Studio',
                'driver' => 'openai',
                'catalog' => 'openai',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'api_key' => null,
                'api_key_env' => 'GEMINI_API_KEY',
                'models' => [
                    'gemini-3.5-flash-lite' => ['name' => 'Flash-Lite'],
                ],
            ],
        ], $overrides)]);
    }
}
