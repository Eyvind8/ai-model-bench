<?php

namespace App\Evaluation\Judge;

use Illuminate\Support\Str;

/**
 * Criteria parsed from the scenario's judge_rubric: "accuracy (no missing facts), style" ->
 * ['accuracy' => 'accuracy (no missing facts)', 'style' => 'style'].
 */
final class JudgeCriteria
{
    private const DEFAULT_RUBRIC = 'correctness, completeness, clarity';

    /**
     * @return array<string, string> key => description
     */
    public static function fromRubric(?string $rubric): array
    {
        $criteria = [];
        // split on commas outside parentheses: "accuracy (a, b), style"
        foreach (preg_split('/,(?![^()]*\))/', $rubric ?: self::DEFAULT_RUBRIC) ?: [] as $part) {
            $label = trim($part);
            $key = Str::slug(trim(Str::before($label, '(')), '_');
            if ($key !== '') {
                $criteria[$key] = $label;
            }
        }

        return $criteria ?: self::fromRubric(self::DEFAULT_RUBRIC);
    }
}
