<?php

namespace App\Runs;

use App\Llm\ProviderRegistry;
use App\Models\Dataset;
use App\Models\LlmModel;
use App\Models\Scenario;
use App\Services\Models\ModelSelector;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resolves user input (slugs, ids, patterns) into a RunRequest.
 */
final class RunRequestFactory
{
    public function __construct(
        private readonly ModelSelector $selector,
        private readonly ProviderRegistry $registry,
    ) {}

    /**
     * @param  list<string>  $modelPatterns  empty = all enabled models
     *
     * @throws InvalidArgumentException
     */
    public function make(
        string $scenarioSlug,
        ?int $datasetId,
        ?string $input,
        array $modelPatterns = [],
        int $repeats = 1,
        ?int $limit = null,
    ): RunRequest {
        if (($datasetId === null) === ($input === null)) {
            throw new InvalidArgumentException('Pass exactly one of --dataset or --input / --input-file');
        }

        return new RunRequest(
            scenario: $this->scenario($scenarioSlug),
            models: $this->models($modelPatterns),
            dataset: $datasetId !== null ? $this->dataset($datasetId) : null,
            adhocInput: $input,
            repeats: $repeats,
            limit: $limit,
        );
    }

    private function scenario(string $slug): Scenario
    {
        return Scenario::query()->where('slug', $slug)->where('available', true)->first()
            ?? throw new InvalidArgumentException("Scenario \"{$slug}\" not found. Run bench:scenarios:sync");
    }

    private function dataset(int $id): Dataset
    {
        return Dataset::query()->find($id) ?? throw new InvalidArgumentException("Dataset #{$id} not found");
    }

    /**
     * @param  list<string>  $patterns
     * @return Collection<int, LlmModel>
     */
    private function models(array $patterns): Collection
    {
        $models = $patterns !== []
            ? $this->selector->select($patterns)
            : LlmModel::query()->active()->with('provider')->orderBy('provider_id')->orderBy('model_id')->get()
                ->filter(fn (LlmModel $m) => $this->isUsable($m->provider->code))->values();

        return $models->isEmpty()
            ? throw new InvalidArgumentException('No models selected. Add API keys, use --models or bench:models:enable')
            : $models;
    }

    /**
     * Default selection skips providers without API key: every request would fail.
     */
    private function isUsable(string $code): bool
    {
        return $this->registry->has($code) && $this->registry->get($code)->isConfigured();
    }
}
