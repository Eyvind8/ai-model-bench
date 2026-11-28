<?php

namespace App\Services\Models;

use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Selects stored models by "provider:glob" patterns, e.g. "groq:qwen/*", "*flash*".
 */
final class ModelSelector
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    /**
     * @param  list<string>  $patterns
     * @return Collection<int, LlmModel>
     */
    public function select(array $patterns): Collection
    {
        $refs = array_map(fn (string $p) => ModelRef::parse($p, $this->registry->codes()), $patterns);

        return LlmModel::query()
            ->with('provider')
            ->where('available', true)
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get()
            ->filter(fn (LlmModel $model) => $this->registry->has($model->provider->code) && $this->matchesAny($model, $refs))
            ->values();
    }

    /**
     * @param  list<ModelRef>  $refs
     */
    private function matchesAny(LlmModel $model, array $refs): bool
    {
        foreach ($refs as $ref) {
            if (($ref->provider === null || $ref->provider === $model->provider->code) && Str::is($ref->model, $model->model_id)) {
                return true;
            }
        }

        return false;
    }
}
