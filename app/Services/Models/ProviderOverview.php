<?php

namespace App\Services\Models;

use App\Llm\ProviderConfig;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;

/**
 * Provider cards for dashboard / models page: key state and model counts.
 */
final class ProviderOverview
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    /**
     * @return list<array{code: string, name: string, configured: bool, reason: ?string, enabled: int, total: int}>
     */
    public function rows(): array
    {
        $counts = LlmModel::query()
            ->join('providers', 'providers.id', '=', 'models.provider_id')
            ->where('models.available', true)
            ->groupBy('providers.code')
            ->selectRaw('providers.code as code, count(*) as total, sum(models.enabled) as enabled')
            ->get()
            ->keyBy('code');

        return array_map(fn (ProviderConfig $p) => [
            'code' => $p->code,
            'name' => $p->name,
            'configured' => $p->isConfigured(),
            'reason' => $p->missingReason(),
            'enabled' => (int) ($counts[$p->code]->enabled ?? 0),
            'total' => (int) ($counts[$p->code]->total ?? 0),
        ], $this->registry->all());
    }
}
