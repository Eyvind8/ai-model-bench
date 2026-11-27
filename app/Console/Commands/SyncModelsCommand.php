<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesProviderOption;
use App\Enums\SyncStatus;
use App\Llm\ProviderRegistry;
use App\Services\Models\ModelSyncService;
use App\Services\Models\SyncReport;
use Illuminate\Console\Command;

class SyncModelsCommand extends Command
{
    use ValidatesProviderOption;

    protected $signature = 'bench:models:sync
        {--provider= : Sync only this provider}
        {--discover : Also fetch models from provider catalog APIs}';

    protected $description = 'Sync providers and models from config/bench.php into the DB';

    public function handle(ModelSyncService $sync, ProviderRegistry $registry): int
    {
        $provider = $this->providerOption($registry);
        if ($provider === false) {
            return self::FAILURE;
        }

        $reports = $sync->sync($provider, (bool) $this->option('discover'));

        $this->table(
            ['Provider', 'Status', 'Added', 'Updated', 'Removed', 'Message'],
            array_map(fn (SyncReport $r) => [
                $r->provider, $r->status->value, $r->counts->added, $r->counts->updated, $r->counts->removed, $r->message,
            ], $reports),
        );

        $failed = array_filter($reports, fn (SyncReport $r) => $r->status === SyncStatus::Failed);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
