<?php

namespace App\Http\Controllers;

use App\Datasets\DatasetItemEditor;
use App\Http\Requests\DatasetItemRequest;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DatasetItemController extends Controller
{
    public function store(DatasetItemRequest $request, Dataset $dataset, DatasetItemEditor $editor): RedirectResponse
    {
        $editor->add($dataset, $request->validated());

        return redirect()->route('datasets.show', $dataset)->with('status', 'Статтю додано');
    }

    public function edit(Dataset $dataset, DatasetItem $item): View
    {
        return view('datasets.item-edit', ['dataset' => $dataset, 'item' => $item]);
    }

    public function update(DatasetItemRequest $request, Dataset $dataset, DatasetItem $item, DatasetItemEditor $editor): RedirectResponse
    {
        $editor->update($item, $request->validated());

        return redirect()->route('datasets.show', $dataset)->with('status', 'Збережено');
    }

    public function destroy(Dataset $dataset, DatasetItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('datasets.show', $dataset)->with('status', 'Видалено');
    }
}
