<?php

namespace App\Jobs;

use App\Articles\ArticleImporter;
use App\Models\DatasetItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $itemId,
        public readonly bool $download = true,
    ) {}

    public function handle(ArticleImporter $importer): void
    {
        $item = DatasetItem::query()->find($this->itemId);

        if ($item !== null) {
            $importer->process($item, $this->download);
        }
    }
}
