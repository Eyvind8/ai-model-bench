<?php

use App\Llm\Catalogs\CloudflareCatalog;
use App\Llm\Catalogs\OpenAiModelsCatalog;
use App\Llm\Catalogs\OpenRouterCatalog;
use App\Llm\OpenAiCompatibleClient;

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP defaults
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => (int) env('BENCH_HTTP_TIMEOUT', 60),
        'connect_timeout' => (int) env('BENCH_HTTP_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | `clients`  - how to talk to a provider (implements LlmClientInterface).
    | `catalogs` - how to discover provider models (implements ModelCatalogInterface).
    | New protocol = new class + one line here; providers refer to drivers by name.
    |
    */
    'drivers' => [
        'clients' => [
            'openai' => OpenAiCompatibleClient::class,
        ],
        'catalogs' => [
            'openai' => OpenAiModelsCatalog::class,
            'openrouter' => OpenRouterCatalog::class,
            'cloudflare' => CloudflareCatalog::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers and models
    |--------------------------------------------------------------------------
    |
    | Adding a provider or a model = adding an entry here, no code changes.
    |
    | Provider keys:
    |   driver        client driver name (see `drivers.clients`)
    |   catalog       catalog driver for `bench:models:sync --discover`, null = config models only
    |   base_url      may contain {account_id}, substituted from `account_id`
    |   api_key / api_key_env
    |   headers       extra HTTP headers
    |   exclude       regexes of model IDs skipped during discovery (embeddings, tts, ...)
    |   limits        free-tier defaults: rpm, rpd, tpm, min_interval_ms
    |   model_limits  regex => limits override
    |   models        model ID => options:
    |                   name, context_window, json (supports response_format),
    |                   free (default true), enabled (default true), limits
    |
    | Model IDs below come from the initial research and may be outdated:
    | check them with `bench:models:ping`, discover current ones with `--discover`.
    |
    */
    'providers' => [

        'gemini' => [
            'name' => 'Google AI Studio',
            'driver' => 'openai',
            'catalog' => 'openai',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'api_key' => env('GEMINI_API_KEY'),
            'api_key_env' => 'GEMINI_API_KEY',
            'exclude' => [
                '/embed/i', '/imagen/i', '/veo/i', '/tts/i', '/aqa/i', '/image/i',
                '/audio/i', '/live/i', '/lyria/i', '/robotics/i', '/computer-use/i',
            ],
            'limits' => ['rpm' => 5, 'rpd' => 20, 'tpm' => null, 'min_interval_ms' => 3000],
            'model_limits' => [
                '/flash-lite/i' => ['rpm' => 15, 'rpd' => 500],
                '/gemma/i' => ['rpm' => 30, 'rpd' => 14400],
            ],
            'models' => [
                'gemini-3.5-flash-lite' => ['name' => 'Gemini 3.5 Flash-Lite', 'json' => true],
                'gemini-3.8-flash' => ['name' => 'Gemini 3.8 Flash', 'json' => true],
                'gemma-4-31b-it' => ['name' => 'Gemma 4 31B'],
                'gemma-4-26b-a4b-it' => ['name' => 'Gemma 4 26B A4B'],
            ],
        ],

        'groq' => [
            'name' => 'Groq',
            'driver' => 'openai',
            'catalog' => 'openai',
            'base_url' => 'https://api.groq.com/openai/v1',
            'api_key' => env('GROQ_API_KEY'),
            'api_key_env' => 'GROQ_API_KEY',
            'exclude' => ['/whisper/i', '/tts/i', '/guard/i', '/orpheus/i', '/playai/i'],
            'limits' => ['rpm' => 30, 'rpd' => 1000, 'tpm' => 6000, 'min_interval_ms' => 2000],
            'model_limits' => [],
            'models' => [
                'meta-llama/llama-4-scout-17b-16e-instruct' => ['name' => 'Llama 4 Scout', 'json' => true],
                'qwen/qwen3-32b' => ['name' => 'Qwen3 32B', 'json' => true],
            ],
        ],

        'openrouter' => [
            'name' => 'OpenRouter',
            'driver' => 'openai',
            'catalog' => 'openrouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => env('OPENROUTER_API_KEY'),
            'api_key_env' => 'OPENROUTER_API_KEY',
            'headers' => ['X-Title' => 'AI Model Bench'],
            'exclude' => [],
            'limits' => ['rpm' => 20, 'rpd' => 50, 'tpm' => null, 'min_interval_ms' => 3000],
            'model_limits' => [],
            'models' => [
                'openrouter/free' => ['name' => 'OpenRouter auto (free)'],
            ],
        ],

        'cloudflare' => [
            'name' => 'Cloudflare Workers AI',
            'driver' => 'openai',
            'catalog' => 'cloudflare',
            'base_url' => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/v1',
            'api_key' => env('CF_API_TOKEN'),
            'api_key_env' => 'CF_API_TOKEN',
            'account_id' => env('CF_ACCOUNT_ID'),
            'account_id_env' => 'CF_ACCOUNT_ID',
            'exclude' => [],
            'limits' => ['rpm' => 300, 'rpd' => null, 'tpm' => null, 'min_interval_ms' => 1000],
            'model_limits' => [],
            'models' => [
                '@cf/meta/llama-3.3-70b-instruct-fp8-fast' => ['name' => 'Llama 3.3 70B (CF)', 'context_window' => 24000],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ping
    |--------------------------------------------------------------------------
    */
    'ping' => [
        'prompt' => 'Reply with exactly one word: pong',
        'max_tokens' => 64,
    ],
];
