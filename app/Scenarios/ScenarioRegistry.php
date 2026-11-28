<?php

namespace App\Scenarios;

use Illuminate\Contracts\Config\Repository;

/**
 * Scenario definitions from config/scenarios.php.
 */
final class ScenarioRegistry
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return list<ScenarioDefinition>
     */
    public function all(): array
    {
        $definitions = [];
        foreach ($this->config->get('scenarios', []) as $slug => $data) {
            $definitions[] = ScenarioDefinition::fromArray((string) $slug, $data);
        }

        return $definitions;
    }
}
