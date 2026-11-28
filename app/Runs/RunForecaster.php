<?php

namespace App\Runs;

use App\Contracts\RateLimiterInterface;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;

/**
 * "This run needs X requests per model, Y are left today" before anything is sent.
 */
final class RunForecaster
{
    public function __construct(
        private readonly RunInputResolver $inputs,
        private readonly ProviderRegistry $registry,
        private readonly RateLimiterInterface $limiter,
    ) {}

    /**
     * @return list<ForecastLine>
     */
    public function forecast(RunRequest $request): array
    {
        $perModel = count($this->inputs->resolve($request)) * $request->repeats;

        return $request->models->map(function (LlmModel $model) use ($perModel) {
            $code = $model->provider->code;
            $quota = $this->registry->has($code) ? $this->limiter->remaining($this->registry->get($code), $model->model_id) : null;

            return new ForecastLine($model->ref(), $perModel, $quota?->rpd, $quota->exhausted ?? false);
        })->values()->all();
    }
}
