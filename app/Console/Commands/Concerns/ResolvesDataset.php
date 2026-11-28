<?php

namespace App\Console\Commands\Concerns;

use App\Models\Dataset;

/**
 * --name (create) / --dataset (existing id) handling for dataset commands.
 */
trait ResolvesDataset
{
    use FindsDataset;

    protected function datasetFromOptions(): ?Dataset
    {
        $id = $this->option('dataset');
        $name = $this->option('name');

        if (($id === null) === ($name === null)) {
            $this->error('Pass exactly one of --name=<new dataset> or --dataset=<id>');

            return null;
        }

        return $id !== null ? $this->findDataset($id) : Dataset::query()->create(['name' => $name]);
    }
}
