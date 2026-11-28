<?php

namespace App\Runs;

use App\Enums\EvaluationType;
use App\Models\Evaluation;
use App\Models\Result;

/**
 * Manual 1-5 rating of an answer; one per result, re-rating overwrites.
 */
final class HumanRating
{
    public const METRIC = 'rating';

    public function rate(Result $result, int $rating, ?string $comment): Evaluation
    {
        return $result->evaluations()->updateOrCreate(
            ['type' => EvaluationType::Human, 'metric' => self::METRIC],
            ['value' => $rating, 'details' => $comment !== null && trim($comment) !== '' ? trim($comment) : null],
        );
    }
}
