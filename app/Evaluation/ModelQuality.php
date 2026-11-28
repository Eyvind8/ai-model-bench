<?php

namespace App\Evaluation;

use App\Models\LlmModel;

final readonly class ModelQuality
{
    public function __construct(
        public LlmModel $model,
        public ?ReferenceAccuracy $accuracy,
        public ?float $repeatSpread,
        public ?float $medianDeviation,
        /** @var array<string, float> judge model ref => mean overall score */
        public array $judgeScores,
        public ?float $humanRating,
        public ?EloRating $elo,
    ) {}
}
