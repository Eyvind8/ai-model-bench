<?php

namespace App\Http\Controllers;

use App\Articles\ArticleFetchException;
use App\Articles\ArticleImporter;
use App\Datasets\DatasetImporter;
use App\Datasets\DatasetStats;
use App\Enums\FetchStatus;
use App\Models\Dataset;
use App\Models\DatasetItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DatasetController extends Controller
{
    public function index(DatasetStats $stats): View
    {
        return view('datasets.index', [
            'datasets' => Dataset::query()->orderByDesc('id')->get()->map(fn (Dataset $d) => ['dataset' => $d, 'stats' => $stats->forDataset($d)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);

        return redirect()->route('datasets.show', Dataset::query()->create($data));
    }

    public function show(Dataset $dataset, DatasetStats $stats): View
    {
        return view('datasets.show', [
            'dataset' => $dataset,
            'items' => $dataset->items()->orderBy('id')->get(['id', 'title', 'input', 'source_url', 'expected_score', 'fetch_status', 'fetch_error', 'meta_json']),
            'stats' => $stats->forDataset($dataset),
        ]);
    }

    public function destroy(Dataset $dataset): RedirectResponse
    {
        $dataset->delete();

        return redirect()->route('datasets.index')->with('status', "Датасет «{$dataset->name}» видалено");
    }

    public function importUrls(Request $request, Dataset $dataset, ArticleImporter $importer): RedirectResponse
    {
        $request->validate(['urls' => ['required', 'string', 'max:100000']]);
        $urls = array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $request->input('urls')) ?: [])));

        $queued = 0;
        $errors = [];
        foreach ($urls as $url) {
            try {
                $importer->queue($dataset, $url) !== null ? $queued++ : $errors[] = "дублікат: {$url}";
            } catch (ArticleFetchException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return redirect()->route('datasets.show', $dataset)
            ->with('status', "У черзі на завантаження: {$queued}")
            ->with('warnings', $errors);
    }

    public function importFile(Request $request, Dataset $dataset, DatasetImporter $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'extensions:csv,json,jsonl']]);
        $file = $request->file('file');

        try {
            $report = $importer->importFile($dataset, (string) $file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('datasets.show', $dataset)
            ->with('status', "Додано {$report->added}, у черзі URL {$report->queued}, пропущено {$report->skipped}")
            ->with('warnings', $report->errors);
    }

    public function refetch(Dataset $dataset, ArticleImporter $importer): RedirectResponse
    {
        $items = $dataset->items()->where('fetch_status', FetchStatus::Failed)->get();
        $items->each(fn (DatasetItem $item) => $importer->requeue($item, download: true));

        return redirect()->route('datasets.show', $dataset)->with('status', "Повторно в черзі: {$items->count()}");
    }
}
