<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesDataset;
use App\Datasets\DatasetImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportDatasetCommand extends Command
{
    use ResolvesDataset;

    protected $signature = 'bench:dataset:import
        {file : CSV, JSON or JSONL file}
        {--name= : Create a new dataset with this name}
        {--dataset= : Append to an existing dataset (id)}';

    protected $description = 'Import articles into a dataset (rows with only a url are downloaded in the queue)';

    public function handle(DatasetImporter $importer): int
    {
        $dataset = $this->datasetFromOptions();
        if ($dataset === null) {
            return self::FAILURE;
        }

        try {
            $report = $importer->importFile($dataset, (string) $this->argument('file'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Dataset #%d "%s": %d added, %d urls queued, %d skipped',
            $dataset->id, $dataset->name, $report->added, $report->queued, $report->skipped,
        ));
        foreach ($report->errors as $error) {
            $this->warn('  '.$error);
        }

        return self::SUCCESS;
    }
}
