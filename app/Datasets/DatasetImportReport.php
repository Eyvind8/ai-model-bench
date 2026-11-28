<?php

namespace App\Datasets;

final readonly class DatasetImportReport
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $added = 0,
        public int $queued = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {}
}
