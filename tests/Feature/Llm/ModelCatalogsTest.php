<?php

namespace Tests\Feature\Llm;

use App\Llm\Catalogs\CloudflareCatalog;
use App\Llm\Catalogs\OpenAiModelsCatalog;
use App\Llm\Catalogs\OpenRouterCatalog;
use App\Llm\Dto\ModelInfo;
use App\Llm\Exceptions\ProviderException;
use App\Llm\ProviderConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModelCatalogsTest extends TestCase
{
    /**
     * @param  list<string>  $exclude
     */
    private function provider(string $code, string $baseUrl, array $exclude = []): ProviderConfig
    {
        return new ProviderConfig($code, $code, 'openai', $baseUrl, 'key', exclude: $exclude);
    }

    /**
     * @param  list<ModelInfo>  $models
     * @return list<string>
     */
    private function ids(array $models): array
    {
        return array_map(fn (ModelInfo $m) => $m->modelId, $models);
    }

    public function test_gemini_strips_prefix_and_excludes_non_chat_models(): void
    {
        Http::fake(['gemini.test/v1beta/openai/models' => Http::response($this->jsonFixture('catalogs/gemini.json'))]);

        $models = $this->app->make(OpenAiModelsCatalog::class)->listModels(
            $this->provider('gemini', 'https://gemini.test/v1beta/openai', ['/embed/i', '/imagen/i', '/tts/i'])
        );

        $this->assertSame(['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemma-4-31b-it'], $this->ids($models));
        $this->assertTrue($models[0]->isFree);
        $this->assertNull($models[0]->supportsJson);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer key'));
    }

    public function test_groq_skips_inactive_and_reads_context_window(): void
    {
        Http::fake(['groq.test/*' => Http::response($this->jsonFixture('catalogs/groq.json'))]);

        $models = $this->app->make(OpenAiModelsCatalog::class)->listModels(
            $this->provider('groq', 'https://groq.test/openai/v1', ['/whisper/i', '/guard/i'])
        );

        $this->assertSame(['meta-llama/llama-4-scout-17b-16e-instruct', 'qwen/qwen3-32b'], $this->ids($models));
        $this->assertSame(131072, $models[0]->contextWindow);
    }

    public function test_openrouter_returns_only_free_models_with_json_support_flag(): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->jsonFixture('catalogs/openrouter.json'))]);

        $models = $this->app->make(OpenRouterCatalog::class)->listModels(
            $this->provider('openrouter', 'https://openrouter.test/api/v1')
        );

        $this->assertSame(
            ['qwen/qwen3-235b-a22b:free', 'meta-llama/llama-4-maverick:free', 'some/zero-priced'],
            $this->ids($models)
        );
        $this->assertSame('Qwen: Qwen3 235B (free)', $models[0]->displayName);
        $this->assertSame(40960, $models[0]->contextWindow);
        $this->assertTrue($models[0]->supportsJson);
        $this->assertFalse($models[1]->supportsJson);
        $this->assertNull($models[2]->supportsJson);
    }

    public function test_cloudflare_uses_models_search_endpoint(): void
    {
        Http::fake(['api.cf.test/*' => Http::response($this->jsonFixture('catalogs/cloudflare.json'))]);

        $models = $this->app->make(CloudflareCatalog::class)->listModels(
            $this->provider('cloudflare', 'https://api.cf.test/client/v4/accounts/acc-1/ai/v1')
        );

        $this->assertSame(['@cf/meta/llama-3.3-70b-instruct-fp8-fast', '@cf/qwen/qwen3-30b-a3b-fp8'], $this->ids($models));
        $this->assertSame(24000, $models[0]->contextWindow);
        $this->assertNull($models[1]->contextWindow);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.cf.test/client/v4/accounts/acc-1/ai/models/search')
            && $r['task'] === 'Text Generation');
    }

    public function test_catalog_http_error_throws_provider_exception(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

        $this->expectException(ProviderException::class);

        $this->app->make(OpenAiModelsCatalog::class)->listModels(
            $this->provider('groq', 'https://groq.test/openai/v1')
        );
    }

    public function test_cloudflare_reads_all_pages(): void
    {
        $page = fn (string $name, int $page) => [
            'success' => true,
            'result' => [['name' => $name, 'properties' => []]],
            'result_info' => ['page' => $page, 'per_page' => 1, 'total_pages' => 2, 'total_count' => 2],
        ];
        Http::fakeSequence('api.cf.test/*')->push($page('@cf/a', 1))->push($page('@cf/b', 2));

        $models = $this->app->make(CloudflareCatalog::class)->listModels(
            $this->provider('cloudflare', 'https://api.cf.test/client/v4/accounts/acc-1/ai/v1')
        );

        $this->assertSame(['@cf/a', '@cf/b'], $this->ids($models));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['page'] === 2);
    }

    public function test_cloudflare_pagination_is_bounded(): void
    {
        Http::fake(['api.cf.test/*' => Http::response([
            'result' => [['name' => '@cf/a']],
            'result_info' => ['page' => 1, 'total_pages' => 1000],
        ])]);

        $this->app->make(CloudflareCatalog::class)->listModels(
            $this->provider('cloudflare', 'https://api.cf.test/client/v4/accounts/acc-1/ai/v1')
        );

        Http::assertSentCount(CloudflareCatalog::MAX_PAGES);
    }
}
