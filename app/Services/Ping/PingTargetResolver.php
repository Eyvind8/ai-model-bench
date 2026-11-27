<?php

namespace App\Services\Ping;

use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use App\Services\Models\ModelRef;
use InvalidArgumentException;

/**
 * Builds ping targets from an explicit "provider:model" or from enabled models in the DB.
 */
final class PingTargetResolver
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    /**
     * @return list<PingTarget>
     */
    public function resolve(?string $provider, ?string $modelRef): array
    {
        return $modelRef !== null ? [$this->explicit($modelRef)] : $this->enabled($provider);
    }

    private function explicit(string $modelRef): PingTarget
    {
        $ref = ModelRef::parse($modelRef, $this->registry->codes());
        if ($ref->provider === null || $ref->model === '') {
            throw new InvalidArgumentException("Expected provider:model, got \"{$modelRef}\"");
        }

        return new PingTarget($this->registry->get($ref->provider), $ref->model);
    }

    /**
     * @return list<PingTarget>
     */
    private function enabled(?string $provider): array
    {
        $models = LlmModel::query()
            ->active()
            ->with('provider')
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get()
            ->filter(fn (LlmModel $m) => $this->registry->has($m->provider->code)
                && ($provider === null || $m->provider->code === $provider));

        return $models
            ->map(fn (LlmModel $m) => new PingTarget($this->registry->get($m->provider->code), $m->model_id))
            ->values()
            ->all();
    }
}
