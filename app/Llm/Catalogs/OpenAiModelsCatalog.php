<?php

namespace App\Llm\Catalogs;

use App\Contracts\ModelCatalogInterface;
use App\Llm\Dto\ModelInfo;
use App\Llm\ProviderConfig;
use App\Llm\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;

/**
 * Standard OpenAI GET /models (Gemini, Groq). Whole tier is free, so every chat model counts.
 */
final class OpenAiModelsCatalog implements ModelCatalogInterface
{
    public function __construct(private readonly ProviderHttp $http) {}

    public function listModels(ProviderConfig $provider): array
    {
        $data = $this->http
            ->send($provider, fn (PendingRequest $request) => $request->get($provider->url('models')))
            ->json('data', []);

        $models = [];
        foreach ($data as $item) {
            $id = preg_replace('#^models/#', '', (string) ($item['id'] ?? ''));
            if ($id === '' || ($item['active'] ?? true) === false || $provider->isExcluded($id)) {
                continue;
            }

            $models[] = new ModelInfo(
                modelId: $id,
                displayName: $item['display_name'] ?? null,
                contextWindow: isset($item['context_window']) ? (int) $item['context_window'] : null,
            );
        }

        return $models;
    }
}
