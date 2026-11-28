<?php

namespace App\Datasets;

use App\Enums\FetchStatus;
use App\Models\Dataset;
use App\Models\DatasetItem;

final class DatasetStats
{
    /**
     * @return array{int, int, int, int, int} total, ready, pending, failed, labeled
     */
    public function forDataset(Dataset $dataset): array
    {
        $items = $dataset->items();

        return [
            (clone $items)->count(),
            DatasetItem::query()->where('dataset_id', $dataset->id)->ready()->count(),
            (clone $items)->where('fetch_status', FetchStatus::Pending)->count(),
            (clone $items)->where('fetch_status', FetchStatus::Failed)->count(),
            (clone $items)->whereNotNull('expected_score')->count(),
        ];
    }
}
