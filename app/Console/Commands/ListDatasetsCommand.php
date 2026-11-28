<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsDataset;
use App\Datasets\DatasetStats;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ListDatasetsCommand extends Command
{
    use FindsDataset;

    protected $signature = 'bench:dataset:list {dataset? : Show items of this dataset}';

    protected $description = 'List datasets, or items of one dataset with fetch status';

    public function handle(DatasetStats $stats): int
    {
        $id = $this->argument('dataset');

        return $id === null ? $this->datasets($stats) : $this->items($id);
    }

    private function datasets(DatasetStats $stats): int
    {
        $this->table(
            ['ID', 'Name', 'Items', 'Ready', 'Pending', 'Failed', 'Labeled'],
            Dataset::query()->orderBy('id')->get()->map(fn (Dataset $d) => [$d->id, $d->name, ...$stats->forDataset($d)])->all(),
        );

        return self::SUCCESS;
    }

    private function items(mixed $id): int
    {
        $dataset = $this->findDataset($id);
        if ($dataset === null) {
            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Title', 'Chars', 'Score', 'Status', 'Error / URL'],
            $dataset->items()->orderBy('id')->get()->map(fn (DatasetItem $i) => [
                $i->id,
                Str::limit((string) $i->title, 50),
                mb_strlen($i->input),
                $i->expected_score,
                $i->fetch_status->value ?? 'text',
                Str::limit((string) ($i->fetch_error ?? $i->source_url), 70),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
