<?php

namespace App\Console\Commands;

use App\Models\Scenario;
use App\Scenarios\ScenarioSyncService;
use Illuminate\Console\Command;

class SyncScenariosCommand extends Command
{
    protected $signature = 'bench:scenarios:sync';

    protected $description = 'Sync scenarios from config/scenarios.php into the DB and list them';

    public function handle(ScenarioSyncService $sync): int
    {
        $counts = $sync->sync();

        $this->info(sprintf('Scenarios: %d added, %d updated, %d removed', $counts->added, $counts->updated, $counts->removed));
        $this->table(
            ['Slug', 'Name', 'Format', 'Metrics', 'Available'],
            Scenario::query()->orderBy('slug')->get()->map(fn (Scenario $s) => [
                $s->slug,
                $s->name,
                $s->response_format->value,
                implode(', ', array_keys($s->metrics_json ?? [])),
                $s->available ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
