<?php

namespace App\Runs;

use App\Models\DatasetItem;

final class RunInputResolver
{
    /**
     * Ready dataset items (with text) or the single ad-hoc input.
     *
     * @return list<RunInput>
     */
    public function resolve(RunRequest $request): array
    {
        if ($request->dataset === null) {
            return trim((string) $request->adhocInput) === '' ? [] : [new RunInput((string) $request->adhocInput)];
        }

        return DatasetItem::query()
            ->where('dataset_id', $request->dataset->id)
            ->ready()
            ->orderBy('id')
            ->when($request->limit, fn ($q, int $limit) => $q->limit($limit))
            ->get()
            ->map(fn (DatasetItem $item) => RunInput::fromItem($item))
            ->values()
            ->all();
    }
}
