<?php

namespace App\Http\Controllers;

use App\Datasets\DatasetItemEditor;
use App\Http\Requests\LabelItemRequest;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reference labeling: one article at a time, score 1-10 from the keyboard.
 */
class LabelingController extends Controller
{
    public function show(Request $request, Dataset $dataset): View|RedirectResponse
    {
        $items = DatasetItem::query()->where('dataset_id', $dataset->id)->ready()->orderBy('id')->get(['id', 'title', 'input', 'source_url', 'expected_score', 'expected_announcement']);
        if ($items->isEmpty()) {
            return redirect()->route('datasets.show', $dataset)->with('error', 'Немає статей з текстом для розмітки');
        }

        $item = $items->firstWhere('id', $request->integer('item'))
            ?? $items->firstWhere('expected_score', null)
            ?? $items->first();
        $index = $items->search(fn (DatasetItem $i) => $i->id === $item->id);

        return view('datasets.label', [
            'dataset' => $dataset,
            'item' => $item,
            'position' => $index + 1,
            'total' => $items->count(),
            'labeled' => $items->whereNotNull('expected_score')->count(),
            'previous' => $items->get($index - 1),
            'next' => $items->get($index + 1),
        ]);
    }

    public function store(LabelItemRequest $request, Dataset $dataset, DatasetItem $item, DatasetItemEditor $editor): RedirectResponse
    {
        $editor->label($item, $request->integer('expected_score'), $request->input('expected_announcement'));

        $next = DatasetItem::query()->where('dataset_id', $dataset->id)->ready()
            ->where('id', '>', $item->id)->orderBy('id')->value('id');

        return redirect()->route('datasets.label', [$dataset, 'item' => $next ?? $item->id]);
    }
}
