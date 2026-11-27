<?php

namespace App\Console\Commands\Concerns;

use App\Llm\ProviderRegistry;

/**
 * Shared --provider option validation for bench commands.
 */
trait ValidatesProviderOption
{
    /**
     * @return string|false|null provider code, null when not given, false when invalid (error printed)
     */
    protected function providerOption(ProviderRegistry $registry): string|false|null
    {
        $provider = $this->option('provider');
        if ($provider === null || $provider === '') {
            return null;
        }

        if (! $registry->has($provider)) {
            $this->error(sprintf('Unknown provider "%s". Configured: %s', $provider, implode(', ', $registry->codes())));

            return false;
        }

        return $provider;
    }
}
