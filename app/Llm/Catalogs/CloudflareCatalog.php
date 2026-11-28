<?php

namespace App\Llm\Catalogs;

use App\Contracts\ModelCatalogInterface;
use App\Llm\Dto\ModelInfo;
use App\Llm\ProviderConfig;
use App\Llm\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;

/**
 * Cloudflare Workers AI: native models search endpoint, text generation only, paginated.
 */
final class CloudflareCatalog implements ModelCatalogInterface
{
    public const MAX_PAGES = 20;

    private const TASK = 'Text Generation';

    private const PER_PAGE = 100;

    public function __construct(private readonly ProviderHttp $http) {}

    public function listModels(ProviderConfig $provider): array
    {
        $models = [];
        foreach ($this->items($provider) as $item) {
            $id = (string) ($item['name'] ?? '');
            if ($id === '' || $provider->isExcluded($id)) {
                continue;
            }

            $models[] = new ModelInfo(
                modelId: $id,
                displayName: $item['description'] ?? null,
                contextWindow: $this->contextWindow($item['properties'] ?? []),
            );
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(ProviderConfig $provider): array
    {
        $url = (string) preg_replace('#/ai/v1/?$#', '/ai/models/search', $provider->baseUrl);
        $items = [];
        $page = 1;

        do {
            $response = $this->http->send($provider, fn (PendingRequest $request) => $request->get($url, [
                'task' => self::TASK,
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ]));
            array_push($items, ...$response->json('result', []));
            $totalPages = (int) $response->json('result_info.total_pages', 1);
        } while (++$page <= min($totalPages, self::MAX_PAGES));

        return $items;
    }

    /**
     * @param  list<array{property_id?: string, value?: mixed}>  $properties
     */
    private function contextWindow(array $properties): ?int
    {
        foreach ($properties as $property) {
            if (($property['property_id'] ?? null) === 'context_window' && is_numeric($property['value'] ?? null)) {
                return (int) $property['value'];
            }
        }

        return null;
    }
}
