<?php

namespace Tests\Feature\Llm;

use App\Llm\Exceptions\UnknownProviderException;
use App\Llm\ProviderRegistry;
use Tests\TestCase;

class ProviderRegistryTest extends TestCase
{
    private function registry(): ProviderRegistry
    {
        return $this->app->make(ProviderRegistry::class);
    }

    public function test_providers_come_from_config_keys(): void
    {
        $this->useTestProviders();

        $this->assertSame(['groq', 'gemini'], $this->registry()->codes());
        $this->assertCount(2, $this->registry()->all());
        $this->assertTrue($this->registry()->get('groq')->isConfigured());
        $this->assertFalse($this->registry()->get('gemini')->isConfigured());
        $this->assertSame('GEMINI_API_KEY is not set', $this->registry()->get('gemini')->missingReason());
    }

    public function test_new_provider_is_added_by_config_only(): void
    {
        $this->useTestProviders(['mistral' => [
            'name' => 'Mistral',
            'driver' => 'openai',
            'base_url' => 'https://api.mistral.ai/v1',
            'api_key' => 'mk',
            'models' => ['mistral-small-latest' => ['json' => true, 'enabled' => false]],
        ]]);

        $mistral = $this->registry()->get('mistral');

        $this->assertSame('openai', $mistral->driver);
        $this->assertNull($mistral->catalog);
        $this->assertTrue($mistral->isConfigured());
        $this->assertSame('mistral-small-latest', $mistral->models[0]->modelId);
        $this->assertTrue($mistral->models[0]->supportsJson);
        $this->assertFalse($mistral->models[0]->enabled);
    }

    public function test_declared_models_default_to_enabled_and_free(): void
    {
        $this->useTestProviders();

        $model = $this->registry()->get('groq')->models[0];

        $this->assertSame('qwen/qwen3-32b', $model->modelId);
        $this->assertSame('Qwen3 32B', $model->displayName);
        $this->assertTrue($model->enabled);
        $this->assertTrue($model->isFree);
    }

    public function test_unknown_provider_throws(): void
    {
        $this->useTestProviders();

        $this->expectException(UnknownProviderException::class);

        $this->registry()->get('nope');
    }

    public function test_account_id_placeholder(): void
    {
        $this->useTestProviders(['cf' => [
            'base_url' => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/v1',
            'api_key' => 'cf',
            'account_id' => 'acc-42',
        ], 'cf2' => [
            'base_url' => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/v1',
            'api_key' => 'cf',
            'account_id_env' => 'CF_ACCOUNT_ID',
        ]]);

        $this->assertSame('https://api.cloudflare.com/client/v4/accounts/acc-42/ai/v1', $this->registry()->get('cf')->baseUrl);
        $this->assertFalse($this->registry()->get('cf2')->isConfigured());
        $this->assertSame('CF_ACCOUNT_ID is not set', $this->registry()->get('cf2')->missingReason());
    }

    public function test_limits_priority(): void
    {
        $this->useTestProviders(['groq' => [
            'limits' => ['rpm' => 30, 'rpd' => 1000, 'min_interval_ms' => 2000],
            'model_limits' => ['/qwen/' => ['rpm' => 60]],
            'models' => [
                'qwen/qwen3-32b' => ['limits' => ['rpd' => 10]],
                'qwen/other' => [],
            ],
        ]]);

        $groq = $this->registry()->get('groq');

        $this->assertEquals(['rpm' => 60, 'rpd' => 10, 'min_interval_ms' => 2000], $groq->limitsFor('qwen/qwen3-32b'));
        $this->assertEquals(['rpm' => 60, 'rpd' => 1000, 'min_interval_ms' => 2000], $groq->limitsFor('qwen/other'));
        $this->assertEquals(['rpm' => 30, 'rpd' => 1000, 'min_interval_ms' => 2000], $groq->limitsFor('llama'));
    }

    public function test_shipped_config_is_valid(): void
    {
        $registry = $this->registry();

        foreach ($registry->all() as $provider) {
            $this->assertNotSame('', $provider->baseUrl, $provider->code);
            $this->assertNotNull(config("bench.drivers.clients.{$provider->driver}"), $provider->code);
            if ($provider->catalog !== null) {
                $this->assertNotNull(config("bench.drivers.catalogs.{$provider->catalog}"), $provider->code);
            }
        }
    }
}
