<?php

namespace Tests\Feature\Llm;

use App\Llm\Catalogs\OpenRouterCatalog;
use App\Llm\DriverResolver;
use App\Llm\Exceptions\UnknownDriverException;
use App\Llm\OpenAiCompatibleClient;
use App\Llm\ProviderConfig;
use stdClass;
use Tests\TestCase;

class DriverResolverTest extends TestCase
{
    private function provider(string $driver = 'openai', ?string $catalog = null): ProviderConfig
    {
        return new ProviderConfig('p', 'P', $driver, 'https://p.test', 'k', catalog: $catalog);
    }

    public function test_resolves_client_and_catalog_by_name(): void
    {
        $resolver = $this->app->make(DriverResolver::class);

        $this->assertInstanceOf(OpenAiCompatibleClient::class, $resolver->client($this->provider()));
        $this->assertInstanceOf(OpenRouterCatalog::class, $resolver->catalog($this->provider(catalog: 'openrouter')));
        $this->assertNull($resolver->catalog($this->provider()));
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(UnknownDriverException::class);
        $this->expectExceptionMessage('not registered');

        $this->app->make(DriverResolver::class)->client($this->provider('grpc'));
    }

    public function test_driver_must_implement_interface(): void
    {
        config(['bench.drivers.clients.bad' => stdClass::class]);

        $this->expectException(UnknownDriverException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(DriverResolver::class)->client($this->provider('bad'));
    }
}
