<?php

namespace App\Scenarios;

/**
 * Which answer fields to show in tables: derived from the scenario metrics
 * (score_error -> score field, max_length -> text field), so scenarios need no extra config.
 */
final readonly class ScenarioDisplay
{
    public function __construct(private ScenarioDefinition $scenario) {}

    public function scoreField(): ?string
    {
        return $this->fieldOf('score_error') ?? ($this->scenario->expectsJson() ? 'score' : null);
    }

    public function textField(): ?string
    {
        return $this->fieldOf('max_length');
    }

    private function fieldOf(string $metric): ?string
    {
        foreach ($this->scenario->metrics as $options) {
            if (($options['metric'] ?? null) === $metric && isset($options['field'])) {
                return (string) $options['field'];
            }
        }

        return null;
    }
}
