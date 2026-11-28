<?php

namespace App\Evaluation\Judge;

use App\Runs\Execution\JsonResponseParser;

final class JudgeVerdictParser
{
    public function __construct(private readonly JsonResponseParser $json) {}

    /**
     * @param  array<string, string>  $criteria
     */
    public function parse(?string $content, array $criteria): ?JudgeVerdict
    {
        $json = $this->json->extract($content);
        $data = $json !== null ? json_decode($json, true) : null;
        $overall = $data['overall'] ?? null;

        if (! is_numeric($overall) || $overall < 1 || $overall > 10) {
            return null;
        }

        $scores = [];
        foreach (array_keys($criteria) as $key) {
            $value = $data['scores'][$key] ?? null;
            if (is_numeric($value)) {
                $scores[$key] = max(1.0, min(10.0, (float) $value));
            }
        }

        $comment = $data['comment'] ?? null;

        return new JudgeVerdict((float) $overall, $scores, is_string($comment) ? mb_substr($comment, 0, 500) : null);
    }
}
