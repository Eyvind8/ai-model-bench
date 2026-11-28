<?php

namespace App\Runs;

use App\Models\Dataset;
use App\Models\LlmModel;
use App\Models\Scenario;
use Illuminate\Support\Collection;

final readonly class RunRequest
{
    /**
     * @param  Collection<int, LlmModel>  $models
     */
    public function __construct(
        public Scenario $scenario,
        public Collection $models,
        public ?Dataset $dataset = null,
        public ?string $adhocInput = null,
        public int $repeats = 1,
        public ?int $limit = null,
    ) {}
}
