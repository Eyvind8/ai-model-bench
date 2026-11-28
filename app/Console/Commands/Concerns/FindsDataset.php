<?php

namespace App\Console\Commands\Concerns;

use App\Models\Dataset;

trait FindsDataset
{
    protected function findDataset(mixed $id): ?Dataset
    {
        $dataset = Dataset::query()->find((int) $id);
        if ($dataset === null) {
            $this->error("Dataset #{$id} not found. See bench:dataset:list");
        }

        return $dataset;
    }
}
