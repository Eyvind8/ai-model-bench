<?php

namespace App\Console\Commands;

use App\Articles\ArticleImporter;
use App\Console\Commands\Concerns\FindsDataset;
use App\Enums\FetchStatus;
use App\Models\DatasetItem;
use Illuminate\Console\Command;

class RefetchDatasetCommand extends Command
{
    use FindsDataset;

    protected $signature = 'bench:dataset:refetch
        {dataset : Dataset id}
        {--failed : Only failed items}
        {--download : Download again instead of re-extracting stored HTML}';

    protected $description = 'Re-extract (or re-download) articles imported by URL';

    public function handle(ArticleImporter $importer): int
    {
        $dataset = $this->findDataset($this->argument('dataset'));
        if ($dataset === null) {
            return self::FAILURE;
        }

        $items = $dataset->items()
            ->whereNotNull('source_url')
            ->where('fetch_status', '!=', FetchStatus::Manual)
            ->when($this->option('failed'), fn ($q) => $q->where('fetch_status', FetchStatus::Failed))
            ->get();

        $items->each(fn (DatasetItem $item) => $importer->requeue($item, (bool) $this->option('download')));
        $this->info("{$items->count()} item(s) queued");

        return self::SUCCESS;
    }
}
