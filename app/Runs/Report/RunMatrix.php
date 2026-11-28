<?php

namespace App\Runs\Report;

use App\Models\LlmModel;

final readonly class RunMatrix
{
    /**
     * @param  list<MatrixRow>  $rows
     * @param  list<LlmModel>  $models  columns
     */
    public function __construct(
        public array $rows,
        public array $models,
        public ?string $scoreField,
        public ?string $textField,
    ) {}

    public function hasScores(): bool
    {
        return $this->scoreField !== null;
    }
}
