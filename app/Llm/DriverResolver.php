<?php

namespace App\Llm;

use App\Contracts\LlmClientInterface;
use App\Contracts\ModelCatalogInterface;
use App\Llm\Exceptions\UnknownDriverException;
use App\Support\ConfiguredFactory;
use App\Support\InvalidConfigurationException;

/**
 * Resolves client/catalog implementations by driver name from bench.drivers.
 */
final class DriverResolver
{
    public function __construct(private readonly ConfiguredFactory $factory) {}

    /**
     * @throws UnknownDriverException
     */
    public function client(ProviderConfig $provider): LlmClientInterface
    {
        return $this->make('bench.drivers.clients', $provider->driver, LlmClientInterface::class);
    }

    /**
     * @throws UnknownDriverException
     */
    public function catalog(ProviderConfig $provider): ?ModelCatalogInterface
    {
        return $provider->catalog === null
            ? null
            : $this->make('bench.drivers.catalogs', $provider->catalog, ModelCatalogInterface::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $interface
     * @return T
     */
    private function make(string $mapKey, string $name, string $interface): object
    {
        try {
            return $this->factory->make($mapKey, $name, $interface);
        } catch (InvalidConfigurationException $e) {
            throw new UnknownDriverException('Driver '.$e->getMessage(), previous: $e);
        }
    }
}
