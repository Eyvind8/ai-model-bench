<?php

namespace App\Services\Models;

use App\Models\LlmModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Listing and enabling/disabling stored models.
 */
final class ModelManager
{
    public function __construct(private readonly ModelSelector $selector) {}

    /**
     * @return Collection<int, LlmModel>
     */
    public function list(?string $provider = null, bool $onlyEnabled = false): Collection
    {
        return LlmModel::query()
            ->with('provider')
            ->when($provider, fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('code', $provider)))
            ->when($onlyEnabled, fn ($q) => $q->where('enabled', true))
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();
    }

    /**
     * @param  list<string>  $patterns
     * @return Collection<int, LlmModel> affected models
     */
    public function setEnabled(array $patterns, bool $enabled): Collection
    {
        $models = $this->selector->select($patterns);

        if ($models->isNotEmpty()) {
            LlmModel::query()->whereKey($models->modelKeys())->update(['enabled' => $enabled, 'enabled_by_user' => true]);
        }

        return $models;
    }

    public function toggle(LlmModel $model): bool
    {
        $model->update(['enabled' => ! $model->enabled, 'enabled_by_user' => true]);

        return $model->enabled;
    }
}
