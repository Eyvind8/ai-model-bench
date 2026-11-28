<?php

namespace App\Services\Models;

use App\Enums\ModelSource;
use App\Enums\SyncStatus;
use App\Llm\DriverResolver;
use App\Llm\Exceptions\ProviderException;
use App\Llm\ProviderConfig;
use App\Llm\ProviderRegistry;
use App\Models\Provider;

/**
 * Syncs providers and models from config/bench.php into the DB,
 * optionally discovering extra models through provider catalogs.
 */
final class ModelSyncService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly DriverResolver $drivers,
        private readonly ModelCatalogPersister $persister,
    ) {}

    /**
     * @return list<SyncReport>
     */
    public function sync(?string $provider = null, bool $discover = false): array
    {
        $configs = $provider === null ? $this->registry->all() : [$this->registry->get($provider)];

        return array_map(fn (ProviderConfig $config) => $this->syncProvider($config, $discover), $configs);
    }

    private function syncProvider(ProviderConfig $config, bool $discover): SyncReport
    {
        $provider = Provider::query()->updateOrCreate(['code' => $config->code], ['name' => $config->name]);
        $counts = $this->persister->persist($provider, $config->models, ModelSource::Config);

        if (! $discover) {
            return new SyncReport($config->code, SyncStatus::Ok, $counts, $config->missingReason());
        }

        return $this->discover($provider, $config, $counts);
    }

    private function discover(Provider $provider, ProviderConfig $config, SyncCounts $counts): SyncReport
    {
        if ($config->catalog === null) {
            return new SyncReport($config->code, SyncStatus::Ok, $counts, 'no catalog driver, config models only');
        }

        if (! $config->isConfigured()) {
            return new SyncReport($config->code, SyncStatus::Skipped, $counts, $config->missingReason());
        }

        try {
            $found = $this->drivers->catalog($config)?->listModels($config) ?? [];
        } catch (ProviderException $e) {
            return new SyncReport($config->code, SyncStatus::Failed, $counts, $e->getMessage());
        }

        $discovered = $this->persister->persist($provider, $found, ModelSource::Catalog);

        return new SyncReport($config->code, SyncStatus::Ok, $counts->plus($discovered));
    }
}
