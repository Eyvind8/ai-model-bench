<?php

namespace App\Console\Commands;

use App\Articles\ArticleFetchException;
use App\Articles\ArticleImporter;
use App\Console\Commands\Concerns\ResolvesDataset;
use Illuminate\Console\Command;

class FetchUrlsCommand extends Command
{
    use ResolvesDataset;

    protected $signature = 'bench:dataset:fetch-urls
        {urls?* : Article URLs}
        {--file= : Text file with one URL per line}
        {--name= : Create a new dataset with this name}
        {--dataset= : Append to an existing dataset (id)}';

    protected $description = 'Queue articles by URL: download, extract text (Readability), store in a dataset';

    public function handle(ArticleImporter $importer): int
    {
        $urls = $this->urls();
        if ($urls === []) {
            $this->error('Pass URLs as arguments or --file');

            return self::FAILURE;
        }

        $dataset = $this->datasetFromOptions();
        if ($dataset === null) {
            return self::FAILURE;
        }

        $queued = 0;
        foreach ($urls as $url) {
            try {
                $importer->queue($dataset, $url) !== null ? $queued++ : $this->warn("  duplicate: {$url}");
            } catch (ArticleFetchException $e) {
                $this->warn('  '.$e->getMessage());
            }
        }

        $this->info("Dataset #{$dataset->id}: {$queued} url(s) queued. Progress: bench:dataset:list {$dataset->id}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function urls(): array
    {
        $urls = array_values($this->argument('urls'));
        $file = $this->option('file');

        if ($file !== null && is_readable($file)) {
            $urls = array_merge($urls, preg_split('/\R/', (string) file_get_contents($file)) ?: []);
        }

        return array_values(array_unique(array_filter(array_map('trim', $urls), fn (string $u) => $u !== '' && ! str_starts_with($u, '#'))));
    }
}
