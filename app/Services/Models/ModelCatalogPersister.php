<?php

namespace App\Services\Models;

use App\Enums\ModelSource;
use App\Llm\Dto\ModelInfo;
use App\Models\LlmModel;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Upserts a list of models of one source into the `models` table.
 *
 * Rules: user-controlled `enabled` is never overwritten; config models win over
 * catalog ones; models missing from their source become unavailable.
 */
final class ModelCatalogPersister
{
    /**
     * @param  list<ModelInfo>  $models
     */
    public function persist(Provider $provider, array $models, ModelSource $source): SyncCounts
    {
        return $provider->getConnection()->transaction(function () use ($provider, $models, $source) {
            /** @var Collection<string, LlmModel> $existing */
            $existing = $provider->models()->get()->keyBy('model_id');
            $now = CarbonImmutable::now();
            $added = $updated = 0;

            foreach ($models as $info) {
                $model = $existing->get($info->modelId);

                if ($model === null) {
                    $this->create($provider, $info, $source, $now);
                    $added++;
                } elseif ($this->mayUpdate($model, $source)) {
                    $this->update($model, $info, $source, $now);
                    $updated++;
                }
            }

            $removed = $this->markMissing($existing, $models, $source);

            return new SyncCounts($added, $updated, $removed);
        });
    }

    private function mayUpdate(LlmModel $model, ModelSource $source): bool
    {
        return ! ($source === ModelSource::Catalog && $model->source === ModelSource::Config);
    }

    private function create(Provider $provider, ModelInfo $info, ModelSource $source, CarbonImmutable $now): void
    {
        $provider->models()->create([
            'model_id' => $info->modelId,
            'display_name' => $info->displayName,
            'context_window' => $info->contextWindow,
            'supports_json' => $info->supportsJson,
            'is_free' => $info->isFree,
            'source' => $source,
            'available' => true,
            'enabled' => $info->enabled,
            'synced_at' => $now,
        ]);
    }

    private function update(LlmModel $model, ModelInfo $info, ModelSource $source, CarbonImmutable $now): void
    {
        $model->update([
            'display_name' => $info->displayName ?? $model->display_name,
            'context_window' => $info->contextWindow ?? $model->context_window,
            'supports_json' => $info->supportsJson ?? $model->supports_json,
            'is_free' => $info->isFree,
            'source' => $source,
            'available' => true,
            'synced_at' => $now,
        ]);
    }

    /**
     * @param  Collection<string, LlmModel>  $existing
     * @param  list<ModelInfo>  $models
     */
    private function markMissing(Collection $existing, array $models, ModelSource $source): int
    {
        $present = array_map(fn (ModelInfo $info) => $info->modelId, $models);

        $missing = $existing->filter(fn (LlmModel $model) => $model->source === $source
            && $model->available
            && ! in_array($model->model_id, $present, true));

        $missing->each(fn (LlmModel $model) => $model->update(['available' => false]));

        return $missing->count();
    }
}
