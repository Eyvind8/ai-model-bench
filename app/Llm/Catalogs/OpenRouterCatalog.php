<?php

namespace App\Llm\Catalogs;

use App\Contracts\ModelCatalogInterface;
use App\Llm\Dto\ModelInfo;
use App\Llm\ProviderConfig;
use App\Llm\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;

/**
 * OpenRouter catalog: only `:free` or zero-priced models.
 */
final class OpenRouterCatalog implements ModelCatalogInterface
{
    private const JSON_PARAMETERS = ['response_format', 'structured_outputs'];

    public function __construct(private readonly ProviderHttp $http) {}

    public function listModels(ProviderConfig $provider): array
    {
        $data = $this->http
            ->send($provider, fn (PendingRequest $request) => $request->get($provider->url('models')))
            ->json('data', []);

        $models = [];
        foreach ($data as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '' || ! $this->isFree($item) || $provider->isExcluded($id)) {
                continue;
            }

            $models[] = new ModelInfo(
                modelId: $id,
                displayName: $item['name'] ?? null,
                contextWindow: isset($item['context_length']) ? (int) $item['context_length'] : null,
                supportsJson: $this->supportsJson($item),
            );
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isFree(array $item): bool
    {
        if (str_ends_with((string) $item['id'], ':free')) {
            return true;
        }

        $pricing = $item['pricing'] ?? [];

        return isset($pricing['prompt'], $pricing['completion'])
            && (float) $pricing['prompt'] === 0.0
            && (float) $pricing['completion'] === 0.0;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function supportsJson(array $item): ?bool
    {
        if (! isset($item['supported_parameters']) || ! is_array($item['supported_parameters'])) {
            return null;
        }

        return array_intersect(self::JSON_PARAMETERS, $item['supported_parameters']) !== [];
    }
}
