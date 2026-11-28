<?php

namespace App\Llm;

use App\Contracts\LlmClientInterface;
use App\Contracts\ModelCatalogInterface;
use App\Llm\Exceptions\UnknownDriverException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves client/catalog implementations by driver name from bench.drivers.
 */
final class DriverResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    /**
     * @throws UnknownDriverException
     */
    public function client(ProviderConfig $provider): LlmClientInterface
    {
        return $this->make('clients', $provider->driver, LlmClientInterface::class);
    }

    /**
     * @throws UnknownDriverException
     */
    public function catalog(ProviderConfig $provider): ?ModelCatalogInterface
    {
        return $provider->catalog === null
            ? null
            : $this->make('catalogs', $provider->catalog, ModelCatalogInterface::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $interface
     * @return T
     */
    private function make(string $kind, string $name, string $interface): object
    {
        $class = $this->config->get("bench.drivers.{$kind}.{$name}");
        if (! is_string($class) || ! class_exists($class)) {
            throw new UnknownDriverException("Driver \"{$name}\" is not registered in bench.drivers.{$kind}");
        }

        $instance = $this->container->make($class);
        if (! $instance instanceof $interface) {
            throw new UnknownDriverException("{$class} must implement {$interface}");
        }

        return $instance;
    }
}
