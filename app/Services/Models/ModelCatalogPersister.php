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
 * Rules: config models win over catalog ones; models missing from their source become unavailable;
 * `enabled` set manually (enabled_by_user) is never overwritten. The only automatic change of `enabled`:
 * a catalog model the user never touched takes the config's enabled state when it is added to config.
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
            [$added, $updated] = $this->upsertAll($provider, $existing, $models, $source);

            return new SyncCounts($added, $updated, $this->markMissing($existing, $models, $source));
        });
    }

    /**
     * @param  Collection<string, LlmModel>  $existing
     * @param  list<ModelInfo>  $models
     * @return array{int, int} added, updated
     */
    private function upsertAll(Provider $provider, Collection $existing, array $models, ModelSource $source): array
    {
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

        return [$added, $updated];
    }

    /**
     * Catalog data never overrides an active config model, but takes over one dropped from config.
     */
    private function mayUpdate(LlmModel $model, ModelSource $source): bool
    {
        return $source === ModelSource::Config
            || $model->source === ModelSource::Catalog
            || ! $model->available;
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

    /**
     * A catalog model just added to config takes the config's enabled state, unless the user set it manually.
     */
    private function update(LlmModel $model, ModelInfo $info, ModelSource $source, CarbonImmutable $now): void
    {
        $promotedToConfig = $source === ModelSource::Config && $model->source !== ModelSource::Config && ! $model->enabled_by_user;

        $model->update([
            'display_name' => $info->displayName ?? $model->display_name,
            'context_window' => $info->contextWindow ?? $model->context_window,
            'supports_json' => $info->supportsJson ?? $model->supports_json,
            'is_free' => $info->isFree,
            'source' => $source,
            'available' => true,
            'synced_at' => $now,
            ...($promotedToConfig ? ['enabled' => $info->enabled] : []),
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
