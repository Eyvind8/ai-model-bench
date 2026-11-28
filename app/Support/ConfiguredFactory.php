<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Builds an implementation registered in config as `name => class` (drivers, metrics).
 */
final class ConfiguredFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    /**
     * @template T of object
     *
     * @param  string  $mapKey  config key of the name => class map, e.g. "bench.metrics"
     * @param  class-string<T>  $interface
     * @return T
     *
     * @throws InvalidConfigurationException
     */
    public function make(string $mapKey, string $name, string $interface): object
    {
        $class = $this->config->get("{$mapKey}.{$name}");
        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidConfigurationException("\"{$name}\" is not registered in {$mapKey}");
        }

        $instance = $this->container->make($class);
        if (! $instance instanceof $interface) {
            throw new InvalidConfigurationException("{$class} must implement {$interface}");
        }

        return $instance;
    }
}
