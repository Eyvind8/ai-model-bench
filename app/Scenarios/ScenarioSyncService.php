<?php

namespace App\Scenarios;

use App\Models\Scenario;
use App\Services\Models\SyncCounts;

/**
 * Upserts config scenarios into the DB; config scenarios missing from config become unavailable.
 */
final class ScenarioSyncService
{
    private const SOURCE = 'config';

    public function __construct(private readonly ScenarioRegistry $registry) {}

    public function sync(): SyncCounts
    {
        $definitions = $this->registry->all();
        $added = $updated = 0;

        foreach ($definitions as $definition) {
            $scenario = Scenario::query()->updateOrCreate(['slug' => $definition->slug], $this->attributes($definition));
            $scenario->wasRecentlyCreated ? $added++ : $updated++;
        }

        $removed = Scenario::query()
            ->where('source', self::SOURCE)
            ->where('available', true)
            ->whereNotIn('slug', array_map(fn (ScenarioDefinition $d) => $d->slug, $definitions))
            ->update(['available' => false]);

        return new SyncCounts($added, $updated, $removed);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ScenarioDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'system_prompt' => $definition->systemPrompt,
            'user_template' => $definition->userTemplate,
            'response_format' => $definition->responseFormat,
            'json_schema' => $definition->jsonSchema,
            'params_json' => $definition->params,
            'metrics_json' => $definition->metrics,
            'judge_rubric' => $definition->judgeRubric,
            'source' => self::SOURCE,
            'available' => true,
        ];
    }
}
